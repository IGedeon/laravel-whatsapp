<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LaravelWhatsApp\Enums\MessageStatus;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Http\Middleware\VerifyMetaSignature;

it('can receive a text message via webhook', function () {
    
    $stubPath = realpath(__DIR__ . '/../../stubs/webhook_text_message.json');
    $this->assertNotFalse($stubPath, 'Stub file not found');
    $payload = json_decode(file_get_contents($stubPath), true);
    $this->assertIsArray($payload, 'Payload JSON invalid');

    //Disable middleware to avoid signature verification issues during testing
    $this->withoutMiddleware(VerifyMetaSignature::class);
    $response = $this->postJson('/whatsapp/webhook', $payload);

    $response->assertStatus(200);

    // Assert inbound message stored from stub payload
    $this->assertDatabaseHas('whatsapp_messages', [
        'wa_message_id' => 'wamid.HBgMNTczMDA3ODIwNzYyFQIAEhgWM0VCMDBCMDUwMEI5M0E1MjE1RDEyOAA=',
        'type' => 'text',
        'direction' => 'incoming',
    ]);

    $stored = \LaravelWhatsApp\Models\WhatsAppMessage::where('wa_message_id', 'wamid.HBgMNTczMDA3ODIwNzYyFQIAEhgWM0VCMDBCMDUwMEI5M0E1MjE1RDEyOAA=')->first();
    expect($stored)->not->toBeNull();
    expect($stored->getContentProperty('body'))->toBe('Hola Mundo');
});

it('can recieve a image message via webhook', function () {
    
    $stubPath = realpath(__DIR__ . '/../../stubs/webhook_image_message.json');
    $this->assertNotFalse($stubPath, 'Stub file not found');
    $payload = json_decode(file_get_contents($stubPath), true);
    $this->assertIsArray($payload, 'Payload JSON invalid');

    // Mock HTTP calls for media info API
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'url' => 'https://example.com/media/test.jpg',
            'mime_type' => 'image/jpeg',
            'sha256' => 'fake_hash',
            'file_size' => 1024
        ], 200),
        'example.com/*' => Http::response('fake image data', 200),
    ]);

    //Disable middleware to avoid signature verification issues during testing
    $this->withoutMiddleware(VerifyMetaSignature::class);
    $response = $this->postJson('/whatsapp/webhook', $payload);

    $response->assertStatus(200);
    $this->assertDatabaseHas('whatsapp_messages', [
        'wa_message_id' => 'wamid.HBgMNTczMDA3ODIwNzYyFQIAEhggQUNDNjFENDhENTY1QTRERjZENTNCNzhDNjdFOTEyRkYA',
        'type' => 'image',
        'direction' => 'incoming',
    ]);

    //Message content assertions
    $stored = \LaravelWhatsApp\Models\WhatsAppMessage::where('wa_message_id', 'wamid.HBgMNTczMDA3ODIwNzYyFQIAEhggQUNDNjFENDhENTY1QTRERjZENTNCNzhDNjdFOTEyRkYA')->first();
    expect($stored)->not->toBeNull();
    expect($stored->getContentProperty('id'))->toBe('MEDIA_ID_EXAMPLE');

    // Media record assertions    
    $this->assertDatabaseHas('whatsapp_media_elements', [
        'wa_media_id' => 'MEDIA_ID_EXAMPLE',
        'mime_type' => 'image/jpeg',
    ]);
});



it('can mark a message as read', function () {
    // Preparar número
    $apiPhoneNumber = ApiPhoneNumber::create([
        'name' => 'Test Number',
        'phone_number_id' => '1234567890',
        'display_phone_number' => '15551234567'
    ]);

    // Mensaje a marcar como leído
    $message = \LaravelWhatsApp\Models\WhatsAppMessage::create([
        'api_phone_number_id' => $apiPhoneNumber->id,
        'contact_id' => null,
        'direction' => \LaravelWhatsApp\Enums\MessageDirection::INCOMING,
        'wa_message_id' => 'test-read-message-id',
        'timestamp' => now(),
        'type' => \LaravelWhatsApp\Enums\MessageType::TEXT,
        'content' => json_encode(['body' => 'Test message']),
        'status' => \LaravelWhatsApp\Enums\MessageStatus::DELIVERED,
    ]);

    // Reemplazar el fake global para poder hacer aserciones detalladas
    Http::fake([
        '*' => Http::response(['messages' => [['id' => 'any-id']]], 200),
    ]);

    $service = new \LaravelWhatsApp\Services\WhatsAppMessageService();
    $service->markAsRead($message);

    // Estado del modelo
    expect($message->status)->toBe(MessageStatus::READ);

    $expectedUrl = config('whatsapp.base_url') . '/' . config('whatsapp.graph_version') . '/' . $apiPhoneNumber->phone_number_id . '/messages';

    // Asegurar que se envió exactamente una petición
    Http::assertSentCount(1);

    // Asegurar que la petición tiene lo esperado
    Http::assertSent(function (Request $request) use ($expectedUrl) {
        $json = $request->data(); // body enviado como array
        return
            $request->url() === $expectedUrl &&
            $request->method() === 'POST' &&
            $request->hasHeader('Authorization') &&
            str_contains($request->header('Authorization')[0], 'fake-access-token') &&
            $json['messaging_product'] === 'whatsapp' &&
            $json['status'] === 'read' &&
            $json['message_id'] === 'test-read-message-id';
    });
});