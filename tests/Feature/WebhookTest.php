<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use LaravelWhatsApp\Enums\MessageStatus;
use LaravelWhatsApp\Http\Middleware\VerifyMetaSignature;
use LaravelWhatsApp\Models\AccessToken;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\MetaApp;
use LaravelWhatsApp\Models\WhatsAppMessage;
use LaravelWhatsApp\Models\WhatsAppMessageError;

function getWebhookTextPayloadRaw(): string
{
    $stubPath = realpath(__DIR__.'/../../stubs/webhook_text_message.json');
    if ($stubPath === false) {
        throw new Exception('Stub file not found');
    }

    return file_get_contents($stubPath);
}

function getWebhookTextPayloadArray(): array
{
    return json_decode(getWebhookTextPayloadRaw(), true);
}

beforeEach(function () {
    $this->phoneNumber = ApiPhoneNumber::factory()->create([
        'whatsapp_id' => 'test-phone-number-id',
    ]);

    $this->metaApp = MetaApp::factory()->create([
        'name' => 'Test Meta App',
        'meta_app_id' => 'test-meta-app-id',
    ]);

    $this->token = AccessToken::factory()->create([
        'meta_app_id' => $this->metaApp->id,
    ]);

    $this->phoneNumber->businessAccount->accessTokens()->attach($this->metaApp->id);
});

it('can receive a text message via webhook', function () {
    Event::fake();
    $payload = getWebhookTextPayloadArray();
    // Disable middleware to avoid signature verification issues during testing
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
    Event::assertDispatched(\LaravelWhatsApp\Events\WhatsAppMessageReceived::class, function ($event) use ($stored) {
        return $event->message->id === $stored->id;
    });
});

class CustomContactForTest extends \LaravelWhatsApp\Models\Contact
{
    public static $customUsed = false;

    public static function firstOrCreate(array $attributes, array $values = [])
    {
        self::$customUsed = true;

        // Call base Contact model directly to avoid recursion
        return \LaravelWhatsApp\Models\Contact::firstOrCreate($attributes, $values);
    }
}

it('uses custom contact model if configured', function () {
    config(['whatsapp.contact_model' => CustomContactForTest::class]);

    Event::fake();
    $payload = getWebhookTextPayloadArray();
    $this->withoutMiddleware(VerifyMetaSignature::class);
    $this->postJson('/whatsapp/webhook', $payload);

    $stored = \LaravelWhatsApp\Models\WhatsAppMessage::first();

    expect($stored)->not->toBeNull();
    expect($stored->contact)->not->toBeNull();
    expect($stored->contact)->toBeInstanceOf(CustomContactForTest::class);
});

it('can recieve a image message via webhook', function () {
    Event::fake();

    $stubPath = realpath(__DIR__.'/../../stubs/webhook_image_message.json');
    $this->assertNotFalse($stubPath, 'Stub file not found');
    $payload = json_decode(file_get_contents($stubPath), true);
    $this->assertIsArray($payload, 'Payload JSON invalid');

    // Mock HTTP calls for media info API
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'url' => 'https://example.com/media/test.jpg',
            'mime_type' => 'image/jpeg',
            'sha256' => 'fake_hash',
            'file_size' => 1024,
        ], 200),
        'example.com/*' => Http::response('fake image data', 200),
    ]);

    // Disable middleware to avoid signature verification issues during testing
    $this->withoutMiddleware(VerifyMetaSignature::class);
    $response = $this->postJson('/whatsapp/webhook', $payload);

    $response->assertStatus(200);
    $this->assertDatabaseHas('whatsapp_messages', [
        'wa_message_id' => 'wamid.HBgMNTczMDA3ODIwNzYyFQIAEhggQUNDNjFENDhENTY1QTRERjZENTNCNzhDNjdFOTEyRkYA',
        'type' => 'image',
        'direction' => 'incoming',
    ]);

    // Message content assertions
    $stored = \LaravelWhatsApp\Models\WhatsAppMessage::where('wa_message_id', 'wamid.HBgMNTczMDA3ODIwNzYyFQIAEhggQUNDNjFENDhENTY1QTRERjZENTNCNzhDNjdFOTEyRkYA')->first();
    expect($stored)->not->toBeNull();
    expect($stored->getContentProperty('id'))->toBe('MEDIA_ID_EXAMPLE');

    // Media record assertions
    $this->assertDatabaseHas('whatsapp_media_elements', [
        'wa_media_id' => 'MEDIA_ID_EXAMPLE',
        'mime_type' => 'image/jpeg',
    ]);

    // verificar evento disparado WhatsAppMessageReceived
    Event::assertDispatched(\LaravelWhatsApp\Events\WhatsAppMessageReceived::class, function ($event) use ($stored) {
        return $event->message->id === $stored->id &&
               $event->media !== null &&
               $event->media->wa_media_id === 'MEDIA_ID_EXAMPLE' &&
               $event->mediaDownloaded === true;
    });
});

it('can mark a message as read', function () {

    $message = WhatsAppMessage::factory()->create([
        'api_phone_number_id' => $this->phoneNumber->id,
        'status' => MessageStatus::DELIVERED,
        'direction' => \LaravelWhatsApp\Enums\MessageDirection::INCOMING,
    ]);
    
    // Reemplazar el fake global para poder hacer aserciones detalladas
    Http::fake([
        '*' => Http::response(['messages' => [['id' => 'any-id']]], 200),
    ]);

    $service = new \LaravelWhatsApp\Services\WhatsAppService;
    $service->markAsRead($message);

    // Estado del modelo
    expect($message->status)->toBe(MessageStatus::READ);

    $expectedUrl = config('whatsapp.base_url').'/'.config('whatsapp.graph_version').'/'.$this->phoneNumber->whatsapp_id.'/messages';

    // Asegurar que se envió exactamente una petición
    Http::assertSentCount(1);

    // Asegurar que la petición tiene lo esperado
    Http::assertSent(function (Request $request) use ($expectedUrl, $message) {
        $json = $request->data(); // body enviado como array

        return
            $request->url() === $expectedUrl &&
            $request->method() === 'POST' &&
            $request->hasHeader('Authorization') &&
            !empty($request->header('Authorization')[0]) &&
            str_contains($request->header('Authorization')[0], $this->token->access_token) &&
            $json['messaging_product'] === 'whatsapp' &&
            $json['status'] === 'read' &&
            $json['message_id'] === $message->wa_message_id;
    });
});

class CustomApiPhoneNumberForTest extends \LaravelWhatsApp\Models\ApiPhoneNumber
{
    public static $customUsed = false;

    public static function firstOrCreate(array $attributes, array $values = [])
    {
        self::$customUsed = true;

        // Call base ApiPhoneNumber model directly to avoid recursion
        return \LaravelWhatsApp\Models\ApiPhoneNumber::firstOrCreate($attributes, $values);
    }
}

it('uses custom ApiPhoneNumber model if configured', function () {
    config(['whatsapp.apiphone_model' => CustomApiPhoneNumberForTest::class]);

    Event::fake();
    $payload = getWebhookTextPayloadArray();
    $this->withoutMiddleware(VerifyMetaSignature::class);
    $this->postJson('/whatsapp/webhook', $payload);

    $stored = \LaravelWhatsApp\Models\WhatsAppMessage::first();
    expect($stored)->not->toBeNull();
    expect($stored->apiPhoneNumber)->not->toBeNull();
    expect($stored->apiPhoneNumber)->toBeInstanceOf(CustomApiPhoneNumberForTest::class);
});

it('allows request with valid signature', function () {
    $payloadArray = getWebhookTextPayloadArray();
    $rawPayload = json_encode($payloadArray);
    $appSecret = $this->metaApp->app_secret;
    $signature = hash_hmac('sha256', $rawPayload, $appSecret);
    $headers = [
        'X-Hub-Signature-256' => 'sha256='.$signature,
        'Content-Type' => 'application/json',
    ];
    // Middleware enabled by default
    $response = $this->postJson('/whatsapp/webhook', $payloadArray, $headers);
    $response->assertStatus(200); // 200 because payload is valid and signature is accepted
});

it('rejects request with missing signature', function () {
    $payloadArray = getWebhookTextPayloadArray();
    $headers = [
        'Content-Type' => 'application/json',
    ];
    // Middleware enabled by default
    $response = $this->postJson('/whatsapp/webhook', $payloadArray, $headers);
    $response->assertStatus(401);
    $response->assertSee('Missing signature');
});

it('rejects request with invalid signature', function () {
    $payloadArray = getWebhookTextPayloadArray();
    $headers = [
        'X-Hub-Signature-256' => 'sha256=invalidsignature',
        'Content-Type' => 'application/json',
    ];
    // Middleware enabled by default
    $response = $this->postJson('/whatsapp/webhook', $payloadArray, $headers);
    $response->assertStatus(401);
    $response->assertSee('Invalid signature');
});


it('can recieve errors via webhook', function () {
    $this->withoutMiddleware(\LaravelWhatsApp\Http\Middleware\VerifyMetaSignature::class);
    $stubPath = realpath(__DIR__.'/../../stubs/webhook_failed_to_be_delivered.json');
    $this->assertNotFalse($stubPath, 'Stub file not found');
    $payload = json_decode(file_get_contents($stubPath), true);
    $this->assertIsArray($payload, 'Payload JSON invalid');

    $message = WhatsAppMessage::factory()->create([
        'wa_message_id' => 'wamid.fiailed_to_be_delivered',
        'status' => MessageStatus::SENT,
    ]);

    $response = $this->post('/whatsapp/webhook', $payload);
    $response->assertStatus(200);

    $message->refresh();

    $error = WhatsAppMessageError::first();
    expect($error)->not()->toBeNull();

    expect($message->status)->toBe(MessageStatus::FAILED);
    expect($message->errors->first())->toBeInstanceOf(WhatsAppMessageError::class);
    expect($message->errors->first()->code)->toBe('131049');
    expect($error->Message)->not()->toBeNull();
    expect($error->Message)->toBeInstanceOf(WhatsAppMessage::class);
});
