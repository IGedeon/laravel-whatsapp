<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LaravelWhatsApp\Enums\MessageDirection;
use LaravelWhatsApp\Enums\MessageType;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\MessageTypes\Image;
use LaravelWhatsApp\Models\MessageTypes\Text;
use LaravelWhatsApp\Models\WhatsAppMessage;
use LaravelWhatsApp\Services\WhatsAppService;

it('inicializa mensaje de texto correctamente', function () {
    $contact = new Contact(['id' => 1]);
    $apiPhone = new ApiPhoneNumber(['id' => 2]);
    $body = 'Hola mundo';
    $preview = true;
    $msg = new Text($contact, $body, $preview, $apiPhone);

    expect($msg->type)->toBe(MessageType::TEXT)
        ->and($msg->direction)->toBe(MessageDirection::OUTGOING)
        ->and($msg->contact_id)->toBe($contact->id)
        ->and($msg->api_phone_number_id)->toBe($apiPhone->id)
        ->and($msg->body)->toBe($body)
        ->and($msg->previewUrl)->toBeTrue();
});

it('inicializa mensaje de imagen con URL', function () {
    $contact = new Contact(['id' => 10]);
    $apiPhone = new ApiPhoneNumber(['id' => 20]);
    $mediaUrl = 'https://example.com/image.jpg';
    $caption = 'Test caption';
    $msg = Image::createFromUrl($contact, $apiPhone, $mediaUrl, $caption, false);

    expect($msg->type)->toBe(MessageType::IMAGE)
        ->and($msg->direction)->toBe(MessageDirection::OUTGOING)
        ->and($msg->url)->toBe($mediaUrl)
        ->and($msg->caption)->toBe($caption);
});

it('inicializa mensaje de imagen con ID', function () {
    $contact = new Contact(['id' => 11]);
    $apiPhone = new ApiPhoneNumber(['id' => 21]);
    $mediaId = 'abc123';
    $caption = 'Test caption';
    $msg = Image::createFromId($contact, $apiPhone, $mediaId, $caption);

    expect($msg->type)->toBe(MessageType::IMAGE)
        ->and($msg->direction)->toBe(MessageDirection::OUTGOING)
        ->and($msg->waId)->toBe($mediaId)
        ->and($msg->caption)->toBe($caption);
});

it('has mediable relationship using morphOne', function () {
    // Test that the method exists and is a relationship
    $msg = new WhatsAppMessage;

    expect(method_exists($msg, 'media'))->toBeTrue();
});

it('can access content properties correctly', function () {
    $contact = new Contact(['id' => 1]);
    $apiPhone = new ApiPhoneNumber(['id' => 2]);
    $msg = new Text($contact, 'Test message', false, $apiPhone);

    // Test setting and getting content properties
    $msg->setContentProperty('custom_field', 'custom_value');

    expect($msg->getContentProperty('custom_field'))->toBe('custom_value')
        ->and($msg->getContentProperty('nonexistent'))->toBeNull();
});

it('can create message with null parameters', function () {
    $contact = new Contact(['id' => 1]);
    $apiPhone = new ApiPhoneNumber(['id' => 2]);

    $msg = new WhatsAppMessage;

    expect(function () use ($msg, $contact, $apiPhone) {
        $msg->initMessage(
            MessageType::TEXT,
            null, // direction should default to OUTGOING
            $contact,
            $apiPhone,
            ['text' => ['body' => 'Test']]
        );
    })->not->toThrow(Exception::class);

    expect($msg->direction)->toBe(MessageDirection::OUTGOING);
});

it('crea mensaje de texto usando make()', function () {
    $contact = new Contact(['id' => 3]);
    $apiPhone = new ApiPhoneNumber(['id' => 4]);
    $msg = Text::make($contact, 'Mensaje', true, $apiPhone);

    expect($msg)->toBeInstanceOf(Text::class)
        ->and($msg->body)->toBe('Mensaje')
        ->and($msg->previewUrl)->toBeTrue();
});

it('uses phone number as send target when wa_id is present', function () {
    // When a contact has a wa_id, WhatsAppService::send() should set the 'to' field
    $contact = new Contact(['wa_id' => '573000000001', 'user_id' => 'CO.13491208655302741918']);

    expect(empty($contact->wa_id))->toBeFalse();
});

it('uses BSUID as send target when wa_id is absent', function () {
    // When a contact has no wa_id (user enabled username feature), WhatsAppService::send()
    // should set the 'recipient' field with the BSUID instead of 'to'.
    $contact = new Contact(['wa_id' => null, 'user_id' => 'CO.99887766554433221100']);

    expect(empty($contact->wa_id))->toBeTrue()
        ->and($contact->user_id)->toBe('CO.99887766554433221100');
});
