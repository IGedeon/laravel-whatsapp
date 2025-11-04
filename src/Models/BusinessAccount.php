<?php

namespace LaravelWhatsApp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

class BusinessAccount extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_business_accounts';

    protected $fillable = [
        'whatsapp_id',
        'name',
        'currency',
        'timezone_id',
        'message_template_namespace',
        'access_token',
        'subscribed_apps',
    ];

    protected $casts = [
        'subscribed_apps' => 'array',
    ];

    protected $hidden = [
        'access_token',
    ];

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(config('whatsapp.apiphone_model'), 'business_account_id', 'id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class, 'business_account_id', 'id');
    }

    public function accessTokens(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(AccessToken::class, 'whatsapp_business_tokens', 'business_account_id', 'access_token_id');
    }

    public function latestAccessToken(): ?string
    {
        return $this->accessTokens->sortByDesc('created_at')->first()?->access_token;
    }

    public function fillFromMeta($data): self
    {
        $this->loadMissing(['phoneNumbers', 'templates']);

        $this->update([
            'name' => Arr::get($data, 'name'),
            'currency' => Arr::get($data, 'currency'),
            'timezone_id' => Arr::get($data, 'timezone_id'),
            'message_template_namespace' => Arr::get($data, 'message_template_namespace'),
            'subscribed_apps' => Arr::get($data, 'subscribed_apps'),
        ]);

        foreach (Arr::get($data, 'phone_numbers.data', []) as $phoneNumberData) {
            $this->phoneNumbers()->updateOrCreate(
                [
                    'whatsapp_id' => Arr::get($phoneNumberData, 'id'),
                ],
                [
                    'verified_name' => Arr::get($phoneNumberData, 'verified_name'),
                    'code_verification_status' => Arr::get($phoneNumberData, 'code_verification_status'),
                    'display_phone_number' => Arr::get($phoneNumberData, 'display_phone_number'),
                    'platform_type' => Arr::get($phoneNumberData, 'platform_type'),
                    'quality_rating' => Arr::get($phoneNumberData, 'quality_rating'),
                    'throughput_level' => Arr::get($phoneNumberData, 'throughput.level'),
                    'webhook_configuration_application' => Arr::get($phoneNumberData, 'webhook_configuration.application'),
                ]
            );
        }

        foreach (Arr::get($data, 'message_templates.data', []) as $templateData) {
            $this->templates()->updateOrCreate(
                [
                    'whatsapp_id' => Arr::get($templateData, 'id'),
                ],
                [
                    'name' => Arr::get($templateData, 'name'),
                    'message_send_ttl_seconds' => Arr::get($templateData, 'message_send_ttl_seconds'),
                    'parameter_format' => Arr::get($templateData, 'parameter_format'),
                    'components' => Arr::get($templateData, 'components'),
                    'language' => Arr::get($templateData, 'language'),
                    'status' => Arr::get($templateData, 'status'),
                    'category' => Arr::get($templateData, 'category'),
                    'sub_category' => Arr::get($templateData, 'sub_category'),
                ]
            );
        }

        return $this->refresh();
    }
}
