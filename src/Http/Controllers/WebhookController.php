<?php

namespace LaravelWhatsApp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use LaravelWhatsApp\Enums\MessageDirection;
use LaravelWhatsApp\Enums\MessageStatus;
use LaravelWhatsApp\Enums\MessageType;
use LaravelWhatsApp\Events\WhatsAppMessageReceived;
use LaravelWhatsApp\Jobs\DownloadMedia;
use LaravelWhatsApp\Jobs\MarkAsRead;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\WhatsAppMessage;

class WebhookController extends Controller
{
    // https://developers.facebook.com/docs/graph-api/webhooks/getting-started#verification-requests
    public function verify(Request $request)
    {
        $request->validate([
            'hub_mode' => 'required|in:subscribe',
            'hub_verify_token' => 'required',
            'hub_challenge' => 'required',
        ]);

        if (config('whatsapp.verify_token') === $request->input('hub_verify_token')) {
            return response($request->input('hub_challenge'), 200);
        }

        return response('Error validating token', 403);
    }

    public static function receiveMessages(ApiPhoneNumber $phoneNumber, array $messages, Collection $contacts)
    {
        foreach ($messages as $messageData) {
            static::receiveMessage($phoneNumber, $messageData, $contacts);
        }
    }

    public static function receiveMessage(ApiPhoneNumber $phoneNumber, array $messageData, Collection $contacts)
    {
        $message = WhatsAppMessage::where('wa_message_id', $messageData['id'])->first();
        if ($message) {
            return $message; // Message already exists
        }

        $contact = $contacts->firstWhere('wa_id', $messageData['from']);

        if (! $contact) {
            throw new \Exception('Contact not found for wa_id: '.$messageData['from']);
        }

        $message = WhatsAppMessage::create([
            'contact_id' => $contact->id,
            'api_phone_number_id' => $phoneNumber->id,
            'direction' => MessageDirection::INCOMING,
            'wa_message_id' => $messageData['id'],
            'timestamp' => $messageData['timestamp'],
            'type' => MessageType::from($messageData['type']),
            'content' => json_encode(Arr::get($messageData, $messageData['type'], [])),
            'status' => MessageStatus::READ,
        ]);

        $contact->last_messages_received_at = $message->timestamp;

        if ($message->type->isMedia()) {
            $media = $message->media()->create([
                'wa_media_id' => $message->getContentProperty('id'),
                'api_phone_number_id' => $phoneNumber->id,
                'url' => null, // Pendiente de obtener
                'mime_type' => $message->getContentProperty('mime_type'),
                'sha256' => $message->getContentProperty('sha256'),
                'file_size' => null, // Pendiente de obtener
                'filename' => null,
            ]);

            DownloadMedia::dispatch($media)
                ->onConnection(config('whatsapp.queue.connection'))
                ->onQueue(config('whatsapp.queue.media_download_queue'));
            // Evento diferido hasta descargar media. No disparamos aquí.
        } else {
            // Mensaje sin media: disparar evento inmediatamente
            WhatsAppMessageReceived::dispatch($message, null, false);

        }

        if (config('whatsapp.mark_messages_as_read_immediately', true)) {
            MarkAsRead::dispatch($message)
                ->onConnection(config('whatsapp.queue.connection'))
                ->onQueue(config('whatsapp.queue.mark_as_read_queue'));
        }

    }

    protected static function receiveStatus(ApiPhoneNumber $phoneNumber, array $statuses)
    {
        foreach ($statuses as $statusData) {
            $message = WhatsAppMessage::where('wa_message_id', $statusData['id'])->first();
            if (! $message) {
                continue;
            }

            $message->status = MessageStatus::from($statusData['status']);
            $message->status_timestamp = $statusData['timestamp'];

            // only included with sent status, and one of either delivered or read status
            if (Arr::get($statusData, 'pricing', null) !== null) {
                $message->pricing_billable = Arr::get($statusData, 'pricing.billable');
                $message->pricing_model = Arr::get($statusData, 'pricing.pricing_model');
                $message->pricing_type = Arr::get($statusData, 'pricing.type');
                $message->pricing_category = Arr::get($statusData, 'pricing.category');
            }

            // only included if failure to send or deliver message
            if (Arr::get($statusData, 'errors', null) !== null) {
                $message->errors()->createMany(Arr::get($statusData, 'errors'));
            }

            $message->save();
        }
    }

    // https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks/reference/messages
    public function receive(Request $request)
    {
        // Esta función asume que el middleware VerifyMetaSignature ya ha validado la firma del webhook

        $data = $request->all();

        $object = $data['object'] ?? null;

        if ($object !== 'whatsapp_business_account') {
            return response('Event not from a WhatsApp Business Account', 400);
        }

        $entries = Arr::get($data, 'entry', []);

        foreach ($entries as $entry) {
            $changes = Arr::get($entry, 'changes', []);

            foreach ($changes as $change) {
                if (Arr::get($change, 'field', '') !== 'messages') {
                    return response('Event not related to messages', 400);
                }

                $changeValue = Arr::get($change, 'value', []);
                $metadata = Arr::get($changeValue, 'metadata', []);

                $phoneNumberId = Arr::get($change, 'value.metadata.phone_number_id', '');

                $apiPhoneModel = config('whatsapp.apiphone_model');

                $apiPhoneNumber = $apiPhoneModel::where('whatsapp_id', $phoneNumberId)->first();
                if (! $apiPhoneNumber) {
                    return response('ApiPhoneNumber not found for phone_number_id: '.$phoneNumberId, 400);
                }

                $apiPhoneNumber->update([
                    'name' => 'Phone Number '.$metadata['phone_number_id'] ?? '',
                    'display_phone_number' => $metadata['display_phone_number'] ?? '',
                ]);

                $statuses = Arr::get($changeValue, 'statuses', null);
                if ($statuses !== null) {
                    return static::receiveStatus($apiPhoneNumber, $statuses);
                }

                $contacts = collect([]);
                $contacts_ = Arr::get($changeValue, 'contacts', []);

                $contactModel = config('whatsapp.contact_model');
                foreach ($contacts_ as $contactData) {
                    $contact = $contactModel::firstOrCreate([
                        'wa_id' => $contactData['wa_id'] ?? '',
                        'name' => $contactData['profile']['name'] ?? '',
                    ]);
                    $contacts->push($contact);
                }

                $messages = Arr::get($changeValue, 'messages', null);
                if ($messages !== null) {

                    static::receiveMessages($apiPhoneNumber, $messages, $contacts);
                }
            }
        }

        return response('EVENT_RECEIVED', 200);

    }
}
