<?php

namespace LaravelWhatsApp\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LaravelWhatsApp\Enums\MimeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaElement extends Model
{
    protected $table = 'whatsapp_media_elements';

    protected $fillable = [
        'api_phone_number_id',
        'mediable_type',
        'mediable_id',
        'wa_media_id',
        'url',
        'mime_type',
        'sha256',
        'file_size',
        'filename',
        'downloaded_at',
        'uploaded_at',
    ];

    protected $casts = [
        'mime_type' => MimeType::class,
        'downloaded_at' => 'datetime',
        'uploaded_at' => 'datetime'
    ];

    public function apiPhoneNumber()
    {
        return $this->belongsTo(ApiPhoneNumber::class, 'api_phone_number_id');
    }

    public function mediable()
    {
        return $this->morphTo('mediable');
    }

    public function getInfo()
    {
        $url = config('whatsapp.base_url') . '/' . config('whatsapp.graph_version') . '/' . $this->wa_media_id . '?phone_number_id=' . $this->apiPhoneNumber->phone_number_id;
        $token = config('whatsapp.access_token');

        $response = Http::retry(times: 3, sleepMilliseconds: 100, when:null, throw:false)->withHeaders([
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json',
        ])->get($url);

        $response = Http::withToken($token)->get($url);
        
        if ($response->failed()) {
            throw new \Exception("WhatsApp Media Info API request failed with status " . $response->status());
        }

        $responseBody = $response->json();

        $mimeType = MimeType::from($responseBody['mime_type'] ?? MimeType::IMAGE_JPEG->value); // fallback to jpeg for tests

        $this->update([
            'url' => $responseBody['url'] ?? null,
            'mime_type' => $mimeType,
            'sha256' => $responseBody['sha256'] ?? null,
            'file_size' => $responseBody['file_size'] ?? null,
            'filename' => $this->filename ?? (uniqid() . '.' . $mimeType->fileExtension()),
        ]);

        $this->fresh();

        return $this;
    }

    public function download()
    {
        if (empty($this->url)) {
            $this->getInfo();
        }

        if($this->updated_at > Carbon::now()->subMinutes(4)){
            // Las urls expiran rápido, necesitamos obtener una nueva url
            $this->getInfo();
        }

        if ($this->url) {
            $downloadResponse = Http::withToken(config('whatsapp.access_token'))
                ->get($this->url);

            if(!$downloadResponse->ok()){
                Log::warning('WhatsApp Media Download failed', [
                    'status' => $downloadResponse->status(),
                    'media_id' => $this->wa_media_id,
                ]);

                return null;
            }

            $diskName = config('whatsapp.download_disk', 'local');

            $upload = Storage::disk($diskName)->put($this->filename, $downloadResponse->body());

            if(!$upload){
                return null;
            }

            $this->update([
                'downloaded_at' => now(),
            ]);

            return $this->filename;
        }

        return null;
    }

    public function upload(string $filePath)
    {
        if($this->wa_media_id){
            return ['id' => $this->wa_media_id];
        }

        $diskName = config('whatsapp.download_disk', 'local');

        // Verificar que el archivo existe
        if (!Storage::disk($diskName)->exists($filePath)) {
            throw new \InvalidArgumentException("El archivo no existe: $filePath");
        }

        $mimeType = MimeType::tryFrom(Storage::disk($diskName)->mimeType($filePath));

        if(!$mimeType){
            throw new \InvalidArgumentException("Mime type no soportado: $filePath");
        }

        $fileContent = Storage::disk($diskName)->get($filePath);

        $tempPath = sys_get_temp_dir() . '/' . uniqid() . '.' . $mimeType->fileExtension();

        file_put_contents($tempPath, $fileContent);

        $url = config('whatsapp.base_url') . '/' . config('whatsapp.graph_version') . '/' . $this->apiPhoneNumber->phone_number_id . '/media';
        $token = config('whatsapp.access_token');
        
        $response = Http::withToken($token)->asMultipart()->post($url, [
            [
                'name' => 'messaging_product',
                'contents' => 'whatsapp'
            ],
            [
                'name' => 'file',
                'contents' => fopen($tempPath, 'r'),
                'headers' => [
                    'Content-Type' => $mimeType->value
                ]
            ]
        ]);

        if (file_exists($tempPath)) {
            unlink($tempPath);
        }

        if ($response->failed()) {
            Log::warning('WhatsApp Media Upload API failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return $response->json();
        }

        $responseBody = $response->json();

        Log::info('WhatsApp Media Upload API response', [
            'status' => $response->status(),
            'body' => $responseBody,
        ]);

        // Actualizar el modelo con la información del media subido
        if (isset($responseBody['id'])) {
            $this->update([
                'wa_media_id' => $responseBody['id'],
                'uploaded_at' => now()
            ]);
        }

        return $responseBody;
    }

    public function getBase64ContentUrl()
    {
        $diskName = config('whatsapp.download_disk', 'local');

        $downloadDisk = Storage::disk($diskName);

        if (!$downloadDisk->exists($this->filename)) {
            $this->download();
        }

        return 'data:' . $this->mime_type->value . ';base64,' . base64_encode($downloadDisk->get($this->filename));
    }
}   