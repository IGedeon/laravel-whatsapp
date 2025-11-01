<?php

use LaravelWhatsApp\Models\MediaElement;
use LaravelWhatsApp\Models\WhatsAppMessage;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Enums\MimeType;
use LaravelWhatsApp\Enums\MessageType;
use LaravelWhatsApp\Enums\MessageDirection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

beforeEach(function () {
    // Mock config values
    Config::set('whatsapp.base_url', 'https://graph.facebook.com');
    Config::set('whatsapp.graph_version', 'v18.0');
    Config::set('whatsapp.access_token', 'test_token');
    Config::set('whatsapp.download_disk', 'local');
});

it('can create a media element with polymorphic relationship', function () {
    $mediaElement = new MediaElement();
    
    // Test that the fillable fields include the new polymorphic fields
    $fillable = $mediaElement->getFillable();
    
    expect($fillable)->toContain('mediable_type', 'mediable_id')
        ->and($fillable)->toContain('uploaded_at')
        ->and($fillable)->not->toContain('message_id'); // Old field should be removed
});

it('casts datetime fields properly', function () {
    $mediaElement = new MediaElement([
        'downloaded_at' => '2025-10-30 10:00:00',
        'uploaded_at' => '2025-10-30 11:00:00'
    ]);

    expect($mediaElement->downloaded_at)->toBeInstanceOf(\Carbon\Carbon::class)
        ->and($mediaElement->uploaded_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('returns early if wa_media_id already exists on upload', function () {
    $apiPhoneNumber = new ApiPhoneNumber(['id' => 1, 'phone_number_id' => '123456789']);
    
    $mediaElement = new MediaElement([
        'api_phone_number_id' => $apiPhoneNumber->id,
        'wa_media_id' => 'existing_media_id'
    ]);
    
    $mediaElement->apiPhoneNumber = $apiPhoneNumber;

    $result = $mediaElement->upload('test/path/file.jpg');

    expect($result)->toBe(['id' => 'existing_media_id']);
});

it('throws exception when file does not exist on upload', function () {
    $apiPhoneNumber = new ApiPhoneNumber(['id' => 1, 'phone_number_id' => '123456789']);
    
    $mediaElement = new MediaElement([
        'api_phone_number_id' => $apiPhoneNumber->id
    ]);
    $mediaElement->apiPhoneNumber = $apiPhoneNumber;

    Storage::fake('local');
    
    expect(fn() => $mediaElement->upload('nonexistent/file.jpg'))
        ->toThrow(\InvalidArgumentException::class, 'El archivo no existe');
});

it('has correct relationship method', function () {
    $mediaElement = new MediaElement();
    
    // Test that mediable relationship exists and returns MorphTo
    expect($mediaElement->mediable())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
});

it('has upload method that checks for existing media id', function () {
    $mediaElement = new MediaElement([
        'wa_media_id' => 'existing_id'
    ]);
    
    // Should return early if media ID already exists
    expect($mediaElement->upload('any_path'))->toBe(['id' => 'existing_id']);
});

it('has correct casts for enum fields', function () {
    $mediaElement = new MediaElement([
        'mime_type' => 'image/jpeg'
    ]);
    
    // Test that mime_type is cast to MimeType enum
    expect($mediaElement->mime_type)->toBeInstanceOf(MimeType::class)
        ->and($mediaElement->mime_type)->toBe(MimeType::IMAGE_JPEG);
});