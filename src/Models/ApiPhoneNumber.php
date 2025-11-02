<?php

namespace LaravelWhatsApp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiPhoneNumber extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_api_phone_numbers';

    protected $fillable = [
        'business_account_id',
        'verified_name',
        'code_verification_status',
        'display_phone_number',
        'platform_type',
        'quality_rating',
        'throughput_level',
        'webhook_configuration_application',
        'whatsapp_id',
    ];

    protected $casts = [
        'webhook_configuration_application' => 'array',
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

    public function businessAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BusinessAccount::class, 'business_account_id', 'id');
    }
}
