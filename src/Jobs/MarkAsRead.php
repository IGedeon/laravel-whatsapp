<?php

namespace LaravelWhatsApp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LaravelWhatsApp\Models\WhatsAppMessage;
use LaravelWhatsApp\Services\WhatsAppService;

class MarkAsRead implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public WhatsAppMessage $message)
    {
        $this->onQueue(config('whatsapp.mark_as_read_queue', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $service = new WhatsAppService;
        $service->markAsRead($this->message);
    }
}
