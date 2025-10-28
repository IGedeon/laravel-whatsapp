<?php

namespace LaravelWhatsApp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use LaravelWhatsApp\Models\WhatsAppMessage;
use LaravelWhatsApp\Services\WhatsAppMessageService;

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
        $service = new WhatsAppMessageService();
        $service->markAsRead($this->message);
    }
}