<?php
namespace LaravelWhatsApp\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApiPhoneNumber extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_api_phone_numbers';

    protected $fillable = [
        'name',
        'display_phone_number',
        'phone_number_id',
    ];
}