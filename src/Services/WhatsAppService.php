<?php

namespace LaravelWhatsApp\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LaravelWhatsApp\Models\ApiPhoneNumber;
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
     * @param  WhatsAppMessage  $whatsAppMessage  Mensaje de WhatsApp a enviar
     */
    public function send(WhatsAppMessage $whatsAppMessage)
    {
        $whatsAppMessage = $whatsAppMessage->changeStatus(\LaravelWhatsApp\Enums\MessageStatus::SENDING);

        $type = strtolower($whatsAppMessage->type->value);
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $whatsAppMessage->contact->wa_id,
            'type' => $type,
            $type => $whatsAppMessage->content,
        ];

        $token = $whatsAppMessage->apiPhoneNumber->businessAccount->latestAccessToken();

        $response = self::apiPostRequest(access_token: $token, uri: '/'.$whatsAppMessage->apiPhoneNumber->whatsapp_id.'/messages', payload: $data);
        if (Arr::get($response, 'error')) {
            $whatsAppMessage = $whatsAppMessage->changeStatus(\LaravelWhatsApp\Enums\MessageStatus::FAILED);

            $whatsAppMessage->errors()->create([
                'code' => Arr::get($response, 'error.code', null),
                'title' => Arr::get($response, 'error.type', null),
                'message' => Arr::get($response, 'error.message', null),
                'error_data' => Arr::get($response, 'error', null),
                'href' => null,
            ]);

            return;
        }

        $whatsAppMessage->wa_message_id = $response['messages'][0]['id'] ?? null;
        $whatsAppMessage = $whatsAppMessage->changeStatus(\LaravelWhatsApp\Enums\MessageStatus::SENT);
        // $whatsAppMessage->save(); //Already saved in changeStatus

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
        if (empty($token)) {
            throw new \Exception('No access token available to mark message as read.');
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
            if (is_array($dataBody)) {
                return $dataBody;
            }

            throw new \Exception('WhatsApp API request failed with status '.$response->status().' and body: '.$response->body());
        }

        $json = $response->json();
        if (! is_array($json)) {
            // Graceful fallback for faked / empty responses in tests
            $json = [];
        }

        return $json;
    }
}
