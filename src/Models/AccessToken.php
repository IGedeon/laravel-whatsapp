<?php

namespace LaravelWhatsApp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AccessToken extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_access_tokens';

    protected $fillable = [
        'meta_app_id',
        'whatsapp_id',
        'name',
        'access_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function metaApp(): BelongsTo
    {
        return $this->belongsTo(MetaApp::class, 'meta_app_id', 'id');
    }

    public function businessAccounts(): BelongsToMany
    {
        return $this->belongsToMany(BusinessAccount::class, 'whatsapp_business_tokens', 'access_token_id', 'business_account_id');
    }
}
