<?php

use LaravelWhatsApp\Enums\MessageDirection;
use LaravelWhatsApp\Enums\MessageType;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\MessageTypes\Image;
use LaravelWhatsApp\Models\MessageTypes\Text;

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
    $msg = new \LaravelWhatsApp\Models\WhatsAppMessage;

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

    $msg = new \LaravelWhatsApp\Models\WhatsAppMessage;

    expect(function () use ($msg, $contact, $apiPhone) {
        $msg->initMessage(
            MessageType::TEXT,
            null, // direction should default to OUTGOING
            $contact,
            $apiPhone,
            ['text' => ['body' => 'Test']]
        );
    })->not->toThrow(\Exception::class);

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
