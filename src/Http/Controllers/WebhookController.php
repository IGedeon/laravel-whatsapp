<?php

namespace LaravelWhatsApp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use LaravelWhatsApp\Enums\MessageDirection;
use LaravelWhatsApp\Enums\MessageStatus;
use LaravelWhatsApp\Enums\MessageType;
use LaravelWhatsApp\Events\WhatsAppMessageReceived;
use LaravelWhatsApp\Jobs\DownloadMedia;
use LaravelWhatsApp\Jobs\MarkAsRead;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\MetaApp;

class WebhookController extends Controller
{
    // https://developers.facebook.com/docs/graph-api/webhooks/getting-started#verification-requests
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if (empty($mode) || empty($token) || empty($challenge)) {
            return response('Missing parameters', 400);
        }

        if ($mode !== 'subscribe') {
            return response('Invalid hub_mode', 400);
        }

        $metaApp = MetaApp::where('verify_token', $token)->first();

        if ($metaApp) {
            return response($challenge, 200);
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
        $messageModel = config('whatsapp.message_model');

        $message = $messageModel::where('wa_message_id', $messageData['id'])->first();
        if ($message) {
            return $message; // Message already exists
        }

        // from_user_id (BSUID) is always present from 2026-03-31.
        // 'from' (phone) may be omitted when the user has enabled the username feature.
        $fromUserId = $messageData['from_user_id'] ?? null;
        $from = $messageData['from'] ?? null;

        $contact = null;
        if ($fromUserId) {
            $contact = $contacts->firstWhere('user_id', $fromUserId);
        }
        if (! $contact && $from) {
            $contact = $contacts->firstWhere('wa_id', $from);
        }

        if (! $contact) {
            throw new \Exception('Contact not found for message from: '.($from ?? $fromUserId));
        }

        $context = null;

        // Agregar contexto si está presente
        // Sirve para mensajes que son respuestas a otros mensajes, o que forman parte de un hilo
        if (Arr::has($messageData, 'context')) {
            $context = json_encode(
                Arr::get($messageData, 'context', []),
                JSON_UNESCAPED_UNICODE
            );
        }

        $message = $messageModel::create([
            'contact_id' => $contact->id,
            'api_phone_number_id' => $phoneNumber->id,
            'direction' => MessageDirection::INCOMING,
            'wa_message_id' => $messageData['id'],
            'timestamp' => $messageData['timestamp'],
            'type' => MessageType::from($messageData['type']),
            'content' => json_encode(
                Arr::get($messageData, $messageData['type'], []),
                JSON_UNESCAPED_UNICODE
            ),
            'context' => $context,
            'status' => MessageStatus::READ,
        ]);

        try {
            $newTimestamp = $message->timestamp;
            $currentTimestamp = $contact->last_messages_received_at;

            if (is_null($currentTimestamp) || $newTimestamp->gt($currentTimestamp)) {
                $contact->last_messages_received_at = $newTimestamp;
                $contact->last_message_id = $message->id;
                $contact->save();
            }
        } catch (\Throwable $th) {
            Log::driver(config('whatsapp.log_driver', 'single'))->error('Error updating contact last_messages_received_at: '.$th->getMessage(), [
                'contact_id' => $contact->id,
                'message_id' => $message->id,
            ]);
        }

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
        $messageModel = config('whatsapp.message_model');

        foreach ($statuses as $statusData) {
            $message = $messageModel::where('wa_message_id', $statusData['id'])->first();
            if (! $message) {
                continue;
            }

            $newStatus = MessageStatus::from($statusData['status']);

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

            $message->changeStatus($newStatus);

            $message->save(); // Already saved in changeStatus
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
                    $userId = $contactData['user_id'] ?? null;
                    $waId = $contactData['wa_id'] ?? null;
                    $name = $contactData['profile']['name'] ?? null;
                    $username = $contactData['profile']['username'] ?? null;

                    $contact = $contactModel::where('api_phone_id', $apiPhoneNumber->id)
                        ->where(function ($query) use ($userId, $waId) {
                            if ($userId) {
                                $query->orWhere('user_id', $userId);
                            }

                            if ($waId) {
                                $query->orWhere('wa_id', $waId);
                            }
                        })
                        ->first();

                    if (! $contact) {
                        $contact = match ($userId != null) {
                            true => $contactModel::create([
                                'api_phone_id' => $apiPhoneNumber->id,
                                'user_id' => $userId,
                                'wa_id' => $waId,
                                'name' => $name,
                                'username' => $username,
                            ]),

                            false => $contactModel::create([
                                'api_phone_id' => $apiPhoneNumber->id,
                                'wa_id' => $waId,
                                'name' => $name,
                                'username' => $username,
                            ]),

                            default => null,
                        };
                    }

                    if ($userId) {
                        // BSUID is available: use it as primary key (guaranteed unique per portfolio+user)

                        // Update phone or username if they became available
                        $dirty = false;

                        if ($contact->user_id !== $userId) {
                            $contact->user_id = $userId;
                            $dirty = true;
                        }

                        if ($waId && empty($contact->wa_id)) {
                            $contact->wa_id = $waId;
                            $dirty = true;
                        }

                        if ($username && $contact->username !== $username) {
                            $contact->username = $username;
                            $dirty = true;
                        }

                        if ($dirty) {
                            $contact->save();
                        }
                    }

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
