<?php

namespace LaravelWhatsApp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LaravelWhatsApp\Events\WhatsAppMessageReceived;
use LaravelWhatsApp\Models\MediaElement;

class DownloadMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public MediaElement $whatsAppMedia)
    {
        $this->onQueue(config('whatsapp.media_download_queue', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->whatsAppMedia->download();

        // Disparar evento indicando que el mensaje ya tiene la media descargada
        if ($this->whatsAppMedia->mediable) {
            WhatsAppMessageReceived::dispatch(
                $this->whatsAppMedia->mediable,
                $this->whatsAppMedia,
                true
            );
        }
    }
}
