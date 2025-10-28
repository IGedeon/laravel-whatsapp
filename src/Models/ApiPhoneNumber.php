<?php

namespace LaravelWhatsApp\Models;

use Illuminate\Database\Eloquent\Model;

class ApiPhoneNumber extends Model
{
    protected $table = 'whatsapp_api_phone_numbers';

    protected $fillable = [
        'name',
        'display_phone_number',
        'phone_number_id',
    ];
}