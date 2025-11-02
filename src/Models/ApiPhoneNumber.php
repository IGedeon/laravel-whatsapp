<?php

namespace LaravelWhatsApp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiPhoneNumber extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_api_phone_numbers';

    protected $fillable = [
        'name',
        'display_phone_number',
        'access_token',
        'phone_number_id',
    ];

    protected $hidden = [
        'access_token',
    ];

    public static function getDefault(): self
    {
        $phoneNumbers = self::all();

        if ($phoneNumbers->isEmpty()) {
            throw new \InvalidArgumentException("No ApiPhoneNumber records found. 'from' must be provided.");
        }

        if ($phoneNumbers->count() > 1) {
            throw new \InvalidArgumentException("ApiPhoneNumber 'from' must be provided when multiple phone numbers exist.");
        }

        return $phoneNumbers->first();
    }
}
