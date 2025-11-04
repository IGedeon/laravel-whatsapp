<?php

namespace LaravelWhatsApp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetaApp extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_meta_apps';

    protected $fillable = [
        'meta_app_id',
        'name',
        'app_secret',
        'verify_token',
    ];

    public function fillFromMeta()
    {
        $service = new \LaravelWhatsApp\Services\WhatsAppService;
        $data = $service->getAppInfo($this);
        $this->update([
            'name' => $data['name'] ?? null,
        ]);

        return $this;
    }
}
