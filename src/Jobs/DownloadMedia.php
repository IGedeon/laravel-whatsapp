<?php

namespace LaravelWhatsApp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use LaravelWhatsApp\Models\MediaElement;
use LaravelWhatsApp\Events\WhatsAppMessageReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;     

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
        if($this->whatsAppMedia->message){
            WhatsAppMessageReceived::dispatch(
                $this->whatsAppMedia->message,
                $this->whatsAppMedia,
                true
            );
        }
    }
}