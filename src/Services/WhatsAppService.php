<?php

namespace LaravelWhatsApp\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LaravelWhatsApp\Models\AccessToken;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\BusinessAccount;
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\WhatsAppMessage;

/**
 * Servicio de alto nivel para enviar mensajes salientes y marcar mensajes entrantes como leídos.
 * Encapsula la construcción de payloads de la Cloud API y el registro en base de datos.
 */
class WhatsAppService
{
    /**
     * Envía un mensaje de texto sencillo.
     *
     * @param  string  $to  Número de destino en formato internacional (sin +)
     * @param  string  $body  Texto del mensaje
     * @param  string|null  $fromLabel  Etiqueta del número configurado (clave en whatsapp.phone_numbers) o null para default
     */
    public function send(WhatsAppMessage $whatsAppMessage)
    {
        $type = strtolower($whatsAppMessage->type->value);
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $whatsAppMessage->contact->wa_id,
            'type' => $type,
            $type => $whatsAppMessage->content,
        ];

        $token = $whatsAppMessage->apiPhoneNumber->businessAccount->latestAccessToken();

        $response = self::apiPostRequest(access_token: $token, uri: '/'.$whatsAppMessage->apiPhoneNumber->id.'/messages', payload: $data);

        $whatsAppMessage->wa_message_id = $response['messages'][0]['id'] ?? null;
        $whatsAppMessage->save();

        return true;
    }

    /**
     * Envía un mensaje de plantilla.
     *
     * @param  string  $templateName  Nombre de la plantilla aprobada
     * @param  string  $languageCode  Código de idioma (ej: es_MX)
     * @param  array  $components  Componentes del template conforme a la API
     * @param  ApiPhoneNumber|null  $from  Si se omite usa el default configurado
     */
    public function sendTemplateMessage(Contact $to, string $templateName, string $languageCode, array $components = [], ?ApiPhoneNumber $from = null): bool
    {
        if (! $from) {
            $class = config('whatsapp.apiphone_model');
            $from = $class::getDefault();
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
            'status' => 'read',
            'message_id' => $message->wa_message_id,
        ];

        if ($typingIndicator) {
            $data['typing_indicator'] = [
                'type' => 'text',
            ];
        }

        $message->status = \LaravelWhatsApp\Enums\MessageStatus::READ;
        $message->status_timestamp = now();

        $token = $message->apiPhoneNumber->businessAccount->latestAccessToken();
        if(empty($token)){
            throw new \Exception("No access token available to mark message as read.");
        }

        self::apiPostRequest(access_token: $token, uri: $message->apiPhoneNumber->whatsapp_id.'/messages', payload: $data);

        return true;
    }

    protected static function baseUrl(): string
    {
        return config('whatsapp.base_url').'/'.config('whatsapp.graph_version').'/';
    }

    public static function apiGetRequest(string $access_token, string $uri): array
    {
        return self::apiRequest(access_token: $access_token, uri: $uri, method: 'GET');
    }

    public static function apiPostRequest(string $access_token, string $uri, array $payload): array
    {
        return self::apiRequest(access_token: $access_token, uri: $uri, payload: $payload, method: 'POST');
    }

    protected static function apiRequest(string $access_token, string $uri, array $payload = [], string $method = 'POST'): array
    {
        $url = self::baseUrl().$uri;

        if (empty($access_token)) {
            throw new \Exception('WhatsApp access token is not configured.');
        }

        $request = Http::retry(times: 3, sleepMilliseconds: 100, when: null, throw: false)->withHeaders([
            'Authorization' => "Bearer $access_token",
            'Content-Type' => 'application/json',
        ]);

        $response = match (strtoupper($method)) {
            'POST' => $request->post($url, $payload),
            'GET' => $request->get($url),
            'DELETE' => $request->delete($url, $payload),
            'PUT' => $request->put($url, $payload),
            default => throw new \Exception('Unsupported HTTP method: '.$method),
        };

        if ($response->failed()) {
            Log::error('WhatsApp API request failed', [
                'request' => $payload,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $dataBody = $response->json();
            if (isset($dataBody['error']['message'])) {
                throw new \Exception('WhatsApp API error: '.$dataBody['error']['message']);
            }

            throw new \Exception('WhatsApp API request failed with status '.$response->status());
        }

        $json = $response->json();
        if (! is_array($json)) {
            // Graceful fallback for faked / empty responses in tests
            $json = [];
        }

        return $json;
    }
}
