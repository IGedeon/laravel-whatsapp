<?php

namespace LaravelWhatsApp\Listeners;

use Illuminate\Support\Facades\Log;
use LaravelWhatsApp\Events\WhatsAppMessageReceived;

/**
 * Listener por defecto de referencia. Puedes publicar y reemplazar.
 */
class HandleWhatsAppMessageReceived
{
    public function handle(WhatsAppMessageReceived $event): void
    {
        // Ejemplo mínimo: loguear
        Log::info('WhatsAppMessageReceived listener', [
            'message_id' => $event->message->id ?? null,
            'wa_message_id' => $event->message->wa_message_id ?? null,
            'media' => $event->media ? $event->media->filename : null,
            'media_downloaded' => $event->mediaDownloaded,
        ]);
    }
}
