<?php

namespace LaravelWhatsApp\Listeners;

use Illuminate\Support\Facades\Log;
use LaravelWhatsApp\Events\WhatsAppMessageStatusChange;

/**
 * Listener por defecto para manejar cambios en el estado de mensajes de WhatsApp.
 */
class HandleWhatsAppMessageStatusChange
{
    public function handle(WhatsAppMessageStatusChange $event): void
    {
        Log::info('WhatsAppMessageStatusChange listener', [
            'message_id' => $event->message->id ?? null,
            'wa_message_id' => $event->message->wa_message_id ?? null,
            'old_status' => $event->oldStatus?->value,
            'new_status' => $event->newStatus?->value,
        ]);
    }
}
