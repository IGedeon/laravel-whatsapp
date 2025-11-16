<?php

namespace LaravelWhatsApp\Listeners;

use Illuminate\Support\Facades\Log;
use LaravelWhatsApp\Events\WhatsAppMessageReceived;

/**
 * Listener por defecto para manejar mensajes recibidos de WhatsApp.
 */
class HandleWhatsAppMessageReceived
{
    public function handle(WhatsAppMessageReceived $event): void
    {
        Log::info('WhatsAppMessageReceived listener', [
            'message_id' => $event->message->id ?? null,
            'wa_message_id' => $event->message->wa_message_id ?? null,
            'media' => $event->media ? $event->media->filename : null,
            'media_downloaded' => $event->mediaDownloaded,
        ]);
    }
}
