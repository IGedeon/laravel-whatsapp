<?php

namespace LaravelWhatsApp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Template extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'name',
        'message_send_ttl_seconds',
        'parameter_format',
        'components',
        'language',
        'status',
        'category',
        'sub_category',
        'whatsapp_id',
        'business_account_id',
    ];

    protected $casts = [
        'components' => 'array',
    ];

    public function businessAccount(): BelongsTo
    {
        return $this->belongsTo(BusinessAccount::class, 'business_account_id', 'id');
    }
}
