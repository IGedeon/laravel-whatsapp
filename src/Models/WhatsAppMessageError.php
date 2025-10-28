<?php

namespace LaravelWhatsApp\Models;

use LaravelWhatsApp\Enums\MessageType;
use Illuminate\Database\Eloquent\Model;
use LaravelWhatsApp\Enums\MessageDirection;

class WhatsAppMessageError extends Model
{
    protected $table = 'whatsapp_message_errors';

    protected $fillable = [
        'message_id',
        'code',
        'title',
        'message',
        'error_data',
        'href',
    ];

    protected $casts = [
        'error_data' => 'array',
    ];

    public function message()
    {
        return $this->belongsTo(WhatsAppMessage::class, 'message_id');
    }

}