<?php

namespace LaravelWhatsApp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_contacts';

    protected $fillable = [
        'api_phone_id',
        'wa_id',
        'user_id',
        'username',
        'name',
        'profile_pic_url',
        'status',
        'last_messages_received_at',
    ];

    protected $casts = [
        'last_messages_received_at' => 'datetime',
    ];

    public function apiPhoneNumber(): BelongsTo
    {
        return $this->belongsTo(config('whatsapp.apiphone_model'), 'api_phone_id');
    }
}
