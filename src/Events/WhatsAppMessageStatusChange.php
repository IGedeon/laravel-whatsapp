<?php

namespace LaravelWhatsApp\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LaravelWhatsApp\Models\WhatsAppMessage;

class WhatsAppMessageStatusChange
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public WhatsAppMessage $message) {}
}
