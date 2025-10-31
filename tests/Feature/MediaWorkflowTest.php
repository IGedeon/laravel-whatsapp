<?php

use LaravelWhatsApp\Models\MediaElement;
use LaravelWhatsApp\Models\WhatsAppMessage;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Enums\MimeType;
use LaravelWhatsApp\Enums\MessageType;
use LaravelWhatsApp\Enums\MessageDirection;
use LaravelWhatsApp\Models\MessageTypes\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Config::set('whatsapp.base_url', 'https://graph.facebook.com');
    Config::set('whatsapp.graph_version', 'v18.0');
    Config::set('whatsapp.access_token', 'test_token');
    Config::set('whatsapp.download_disk', 'local');
    Config::set('whatsapp.default_api_phone_number_id', '123456789');
});

it('can create a complete media workflow from message to upload', function () {
    // Create test data
    $apiPhoneNumber = ApiPhoneNumber::create([
        'phone_number_id' => '123456789',
        'display_phone_number' => '+1234567890',
        'phone_number' => '+1234567890'
    ]);
    
    $contact = Contact::create([
        'wa_id' => '0987654321',
        'name' => 'Test Contact'
    ]);

    // Create a WhatsApp message with media
    $message = new WhatsAppMessage();
    $message->initMessage(
        MessageType::IMAGE,
        MessageDirection::INCOMING,
        $contact,
        $apiPhoneNumber,
        ['image' => ['id' => 'incoming_media_id', 'caption' => 'Test image']]
    );
    $message->save();

    // Create media element through polymorphic relationship
    $mediaElement = $message->mediable()->create([
        'api_phone_number_id' => $apiPhoneNumber->id,
        'wa_media_id' => 'incoming_media_id',
        'mime_type' => MimeType::IMAGE_JPEG,
        'filename' => 'test_image.jpg'
    ]);

    expect($mediaElement->mediable_type)->toBe(WhatsAppMessage::class)
        ->and($mediaElement->mediable_id)->toBe($message->id)
        ->and($mediaElement->wa_media_id)->toBe('incoming_media_id');

    // Test the relationship works both ways
    expect($message->mediable)->toBeInstanceOf(MediaElement::class)
        ->and($message->mediable->id)->toBe($mediaElement->id);

    expect($mediaElement->mediable)->toBeInstanceOf(WhatsAppMessage::class)
        ->and($mediaElement->mediable->id)->toBe($message->id);
});

it('can handle media upload', function () {
    Storage::fake('local');
    
    $apiPhoneNumber = ApiPhoneNumber::create([
        'phone_number_id' => '123456789',
        'display_phone_number' => '+1234567890',
        'phone_number' => '+1234567890'
    ]);

    // Mock the upload API call
    Http::fake([
        '*' => Http::response([
            'id' => 'uploaded_media_id'
        ], 200)
    ]);

    // Test upload
    $newMediaElement = MediaElement::create([
        'api_phone_number_id' => $apiPhoneNumber->id,
        'mediable_type' => 'AnotherModel', 
        'mediable_id' => 2,
        'mime_type' => MimeType::IMAGE_JPEG,
        'filename' => 'to_upload.jpg'
    ]);

    // Put the file to upload
    Storage::disk('local')->put('to_upload.jpg', 'image content to upload');

    $result = $newMediaElement->upload('to_upload.jpg');

    expect($result)->toBe(['id' => 'uploaded_media_id'])
        ->and($newMediaElement->fresh()->wa_media_id)->toBe('uploaded_media_id')
        ->and($newMediaElement->fresh()->uploaded_at)->not->toBeNull();
});

it('handles webhook creation of media elements correctly', function () {
    $apiPhoneNumber = ApiPhoneNumber::create([
        'phone_number_id' => '123456789',
        'display_phone_number' => '+1234567890',
        'phone_number' => '+1234567890'
    ]);
    
    $contact = Contact::create([
        'wa_id' => '0987654321',
        'name' => 'Test Contact'
    ]);

    // Simulate webhook controller creating message with media
    $message = new WhatsAppMessage();
    $message->initMessage(
        MessageType::IMAGE,
        MessageDirection::INCOMING,
        $contact,
        $apiPhoneNumber,
        [
            'image' => [
                'id' => 'webhook_media_id',
                'caption' => 'Image from webhook'
            ]
        ]
    );
    $message->save();

    // Simulate the webhook controller creating media element
    $media = $message->mediable()->create([
        'wa_media_id' => $message->getContentProperty('image')['id'],
        'api_phone_number_id' => $apiPhoneNumber->id,
        'url' => null, // Will be populated later
        'mime_type' => null, // Will be determined on download
        'sha256' => null,
        'file_size' => null,
        'filename' => null,
    ]);

    expect($media->wa_media_id)->toBe('webhook_media_id')
        ->and($media->mediable)->toBeInstanceOf(WhatsAppMessage::class)
        ->and($message->fresh()->mediable)->toBeInstanceOf(MediaElement::class);
});

it('can generate base64 content URLs for downloaded media', function () {
    Storage::fake('local');
    Storage::disk('local')->put('test_image.jpg', base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEB'));

    // Create ApiPhoneNumber first to satisfy foreign key
    $apiPhoneNumber = ApiPhoneNumber::create([
        'phone_number_id' => '123456789',
        'display_phone_number' => '+1234567890',
        'phone_number' => '+1234567890'
    ]);

    $mediaElement = MediaElement::create([
        'api_phone_number_id' => $apiPhoneNumber->id,
        'mediable_type' => 'TestModel',
        'mediable_id' => 1,
        'filename' => 'test_image.jpg',
        'mime_type' => MimeType::IMAGE_JPEG
    ]);

    $base64Url = $mediaElement->getBase64ContentUrl();

    expect($base64Url)->toStartWith('data:image/jpeg;base64,')
        ->and($base64Url)->toContain('/9j/4AAQSkZJRgABAQEAYABgAAD');
});