<?php

namespace LaravelWhatsApp\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;
use LaravelWhatsApp\Models\WhatsAppMessage;
use LaravelWhatsApp\Models\MediaElement;

class WhatsAppMessageReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Mensaje de WhatsApp recibido.
     */
    public WhatsAppMessage $message;

    /**
     * Media asociado (si aplica). Puede ser null si el mensaje no tiene media o aún no se ha descargado.
     */
    public ?MediaElement $media;

    /**
     * Indica si el evento fue disparado después de la descarga de la media.
     */
    public bool $mediaDownloaded;

    public function __construct(WhatsAppMessage $message, ?MediaElement $media = null, bool $mediaDownloaded = false)
    {
        $this->message = $message;
        $this->media = $media;
        $this->mediaDownloaded = $mediaDownloaded;
    }
}
