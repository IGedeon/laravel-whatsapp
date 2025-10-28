<?php

namespace LaravelWhatsApp\Services;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use LaravelWhatsApp\Models\Contact;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use LaravelWhatsApp\Enums\MessageType;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Enums\MessageDirection;
use LaravelWhatsApp\Models\WhatsAppMessage;
use LaravelWhatsApp\Contracts\WhatsAppClientContract;
use RuntimeException;

/**
 * Servicio de alto nivel para enviar mensajes salientes y marcar mensajes entrantes como leídos.
 * Encapsula la construcción de payloads de la Cloud API y el registro en base de datos.
 */
class WhatsAppMessageService
{
    

    /**
     * Envía un mensaje de texto sencillo.
     *
     * @param string $to Número de destino en formato internacional (sin +)
     * @param string $body Texto del mensaje
     * @param string|null $fromLabel Etiqueta del número configurado (clave en whatsapp.phone_numbers) o null para default
     */
    public function send(WhatsAppMessage $whatsAppMessage)
    {
        $type = strtolower($whatsAppMessage->type->value);
        $data = [
            'messaging_product' => 'whatsapp',
            'to'                => $whatsAppMessage->contact->wa_id,
            'type'              => $type,
            $type               => $whatsAppMessage->content,
        ];

        $response = self::apiRequest($whatsAppMessage->apiPhoneNumber, $data);

        $whatsAppMessage->wa_message_id = $response['messages'][0]['id'] ?? null;
        $whatsAppMessage->save();

        return true;
    }

    /**
     * Envía un mensaje de plantilla.
     *
     * @param Contact $to
     * @param string $templateName Nombre de la plantilla aprobada
     * @param string $languageCode Código de idioma (ej: es_MX)
     * @param array $components Componentes del template conforme a la API
     * @param ApiPhoneNumber|null $from Si se omite usa el default configurado
     */
    public function sendTemplateMessage(Contact $to, string $templateName, string $languageCode, array $components = [], ?ApiPhoneNumber $from = null): bool
    {
        if ($from === null) {
            $from = ApiPhoneNumber::where('phone_number_id', config('whatsapp.default_api_phone_number_id'))->first();
            if(!$from) {
                throw new \InvalidArgumentException("ApiPhoneNumber default no encontrado. Configure whatsapp.default_api_phone_number_id");
            }
        }

        $message = \LaravelWhatsApp\Models\MessageTypes\Template::create($to, $from, $templateName, $languageCode, $components);
        return $this->send($message);
    }

    /**
     * Marca un mensaje recibido como leído (status read) en la Cloud API.
     */
    public function markAsRead(WhatsAppMessage $message, bool $typingIndicator = false): bool
    {
        $data = [
            'messaging_product' => 'whatsapp',
            'status'            => 'read',
            'message_id'        => $message->wa_message_id,
        ];

        if ($typingIndicator) {
            $data['typing_indicator'] = [
                'type' => 'text'
            ];
        }

        $message->status = \LaravelWhatsApp\Enums\MessageStatus::READ;
        $message->status_timestamp = now();

        self::apiRequest($message->apiPhoneNumber, $data);

        return true;
    }

    protected static function apiRequest(ApiPhoneNumber $phoneNumber, array $data): array
    {
        $url = config("whatsapp.base_url") . "/" . config("whatsapp.graph_version") . "/" . $phoneNumber->phone_number_id . "/messages";
        
        $token = config("whatsapp.access_token");

        if(!$token) {
            throw new \Exception("WhatsApp access token is not configured.");
        }

        $response = Http::retry(times: 3, sleepMilliseconds: 100, when:null, throw:false)->withHeaders([
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json',
        ])->post($url, $data);

        if ($response->failed()) {
            Log::error("WhatsApp API request failed", [
                'request' => $data,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception("WhatsApp API request failed with status " . $response->status());
        }

        $json = $response->json();
        if(!is_array($json)) {
            // Graceful fallback for faked / empty responses in tests
            $json = [];
        }
        return $json;
    }
}
