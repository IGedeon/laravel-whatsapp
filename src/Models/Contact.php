<?php

namespace LaravelWhatsApp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_contacts';

    protected $fillable = [
        'wa_id',
        'name',
        'profile_pic_url',
        'status',
        'last_messages_received_at',
    ];

    protected $casts = [
        'last_messages_received_at' => 'datetime',
    ];
}
