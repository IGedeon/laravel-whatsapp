# ![Tests](https://github.com/IGedeon/laravel-whatsapp/actions/workflows/test.yml/badge.svg)
# Laravel WhatsApp Cloud API (Multi Number)

Library to integrate WhatsApp Cloud API into Laravel applications. Features:

- Support for multiple numbers (Business Phone Numbers)
- Persistence for contacts, messages, conversations, and errors
- Download and upload of media (images, documents, audio, video, stickers)
- Optional queue handling for sending and downloading media
- Utilities to mark messages as read

---

## Installation

```bash
composer require igedeon/laravel-whatsapp
```

Publish configuration and migrations (manual method):

```bash
php artisan vendor:publish --provider="LaravelWhatsApp\\WhatsAppServiceProvider" --tag=whatsapp-config
php artisan vendor:publish --provider="LaravelWhatsApp\\WhatsAppServiceProvider" --tag=whatsapp-migrations
php artisan migrate
```

Or publish everything in one step:

```bash
php artisan vendor:publish --provider="LaravelWhatsApp\\WhatsAppServiceProvider" --tag=whatsapp
```

---

## Webhook Security: Signature Verification

The `VerifyMetaSignature` middleware validates the authenticity of received webhooks using the `X-Hub-Signature-256` header and the secret configured in `whatsapp.app_secret`. If the signature is invalid or the secret is missing, the request will be rejected (401) or an exception will be thrown.

For local testing, you can disable the middleware using:

```php
$this->withoutMiddleware(\LaravelWhatsApp\Http\Middleware\VerifyMetaSignature::class);
```


For integration tests, make sure to generate the signature using:

```php
$signature = hash_hmac('sha256', $rawBody, config('whatsapp.app_secret'));
```

And send the header:

```
X-Hub-Signature-256: sha256=<signature>
```

If the secret is not configured, an exception will be thrown to prevent processing insecure webhooks.

---

## Extending the Contact and ApiPhoneNumber Models

You can extend the `Contact` and `ApiPhoneNumber` models to add custom logic or attributes. Set the model class in the config file:

```php
// config/whatsapp.php
'contact_model' => \App\Models\CustomContact::class,
'apiphone_model' => \App\Models\CustomApiPhoneNumber::class,
```

Both models can be overridden to customize relationships, validations, or methods. The package will use the configured class in all internal processes.

Example of an extended model:

```php
namespace App\Models;

use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\ApiPhoneNumber;

class CustomContact extends Contact
{
	// Add your methods or properties here
}

class CustomApiPhoneNumber extends ApiPhoneNumber
{
	// Add your methods or properties here
}
```

Remember to run `php artisan config:cache` if you use config caching.

---

### Quick Install Command

The package provides a command to simplify installation:


```bash
php artisan whatsapp:install            # Publishes config and migrations
php artisan whatsapp:install --migrate  # Publishes and runs migrations
php artisan whatsapp:install --force    # Forces overwrite of already published files
```

Available flags:

- `--force`: overwrites existing files.
- `--no-config`: does not publish the config file.
- `--no-migrations`: does not publish migrations.
- `--migrate`: runs migrations immediately.

Advanced examples:

```bash
php artisan whatsapp:install --no-config --migrate
php artisan whatsapp:install --no-migrations
```

---

## Environment Variables and Configuration (`config/whatsapp.php`)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `WHATSAPP_ACCESS_TOKEN` | Yes | - | Long-Lived access token generated in Meta to authenticate requests to the Cloud API. Required to send or download media. |
| `WHATSAPP_VERIFY_TOKEN` | Optional (Webhook only) | - | Token to validate Meta's webhook verification. Use if you expose a verification endpoint. |
| `WHATSAPP_APP_SECRET` | Optional | - | App Secret for webhook signature validation. |
| `WHATSAPP_GRAPH_VERSION` | No | `v21.0` | Graph API version used to build Cloud API URLs. Update when Meta releases new features. |
| `WHATSAPP_BASE_URL` | No | `https://graph.facebook.com` | Base host for Graph API. Change only for testing or mocks. |
| `WHATSAPP_DOWNLOAD_DISK` | No | `local` | Laravel disk (see `filesystems.php`) where downloaded files are stored. E.g., `public`, `s3`. |
| `WHATSAPP_QUEUE_CONNECTION` | No | `sync` | Queue connection (see `queue.php`). E.g., `redis`, `database`, `sqs`. |
| `WHATSAPP_MEDIA_DOWNLOAD_QUEUE` | No | `default` | Name of the queue for the `DownloadMedia` Job. |
| `WHATSAPP_MARK_AS_READ_QUEUE` | No | `default` | Name of the queue for the `MarkAsRead` Job. |
| `WHATSAPP_DEFAULT_API_PHONE_NUMBER_ID` | Optional | `null` | Business phone number ID (`phone_number_id`) used by default when creating messages if an `ApiPhoneNumber` is not explicitly provided. |
| `WHATSAPP_DEFAULT_DISPLAY_PHONE_NUMBER` | Optional | `null` | Default display phone number (e.g., +1234567890). |

Notes:

1. For outgoing messages, you need at least one record in the `whatsapp_api_phone_numbers` table (see migration) with its real `phone_number_id` obtained from the Meta panel.
2. If you set `WHATSAPP_DEFAULT_API_PHONE_NUMBER_ID`, the `WhatsAppMessage` model will try to use it automatically when you call `initMessage()` without passing a number.
3. Make sure your `WHATSAPP_ACCESS_TOKEN` is long-lived and updated before it expires.

Minimal `.env` example:

```dotenv
WHATSAPP_ACCESS_TOKEN=EAABxxxxxxxxxxxxxxxxxxxxx
WHATSAPP_DEFAULT_API_PHONE_NUMBER_ID=123456789012345
WHATSAPP_DEFAULT_DISPLAY_PHONE_NUMBER=+1234567890
WHATSAPP_GRAPH_VERSION=v21.0
WHATSAPP_DOWNLOAD_DISK=public
```

---

## Main Model for Sending: `WhatsAppMessage`

Basic flow to send a message:

1. Create (or retrieve) the recipient `Contact` (`wa_id` field = number without +, with country code).
2. Create or retrieve the `ApiPhoneNumber` you send from (if you don't have a default configured).
3. Instantiate `WhatsAppMessage`, initialize it with `initMessage()` passing type and content.
4. Call `send()` on the model (delegates to `WhatsAppMessageService`).

Supported types (enum `MessageType`): `text`, `image`, `video`, `audio`, `document`, `sticker`, `location`, `contacts`, `button`, `interactive`, `reaction`, `order`. Some require specific structure in `content`.

New supported type: `template` for Meta-approved template messages. Use a payload with `template => [ name, language[code], components[] ]`.

Generic structure of `content` sent to the API:

```php
[
  // For text
  'body' => 'Test message',
  // For image/video/document/audio (after uploading media) use 'id' of the media
  // 'id' => 'MEDIA_ID'
]
```

The service automatically builds the payload as:

```json
{
  "messaging_product": "whatsapp",
  "to": "<wa_id>",
  "type": "text|image|...",
  "text|image|video|...": { ... content ... }
}
```

---

## Example: Send a Text Message

```php
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\WhatsAppMessage;
use LaravelWhatsApp\Enums\MessageType;

// 1. Get or create contact
$contact = Contact::firstOrCreate([
	'wa_id' => '5215512345678', // Destination number without '+'
], [
	'name' => 'Juan Perez'
]);

// 2. (Optional) Get sending number if no default
$from = ApiPhoneNumber::where('phone_number_id', env('WHATSAPP_DEFAULT_API_PHONE_NUMBER_ID'))->first();

// 3. Create message instance
$message = new WhatsAppMessage();
$message->initMessage(
	type: MessageType::TEXT,
	to: $contact,
	from: $from, // Can be omitted if default is configured
	contentProps: [
		'body' => 'Hello! This is a test message.'
	]
);

// 4. Send
$message->send();
```

## Example: Send a Template Message

```php
use LaravelWhatsApp\Services\WhatsAppMessageService;
use LaravelWhatsApp\Models\Contact;

$contact = Contact::firstOrCreate(['wa_id' => '5215512345678']);

$service = app(WhatsAppMessageService::class);

$components = [
	[
		'type' => 'body',
		'parameters' => [
			['type' => 'text', 'text' => 'Juan'],
			['type' => 'text', 'text' => 'Order #1234'],
		]
	],
	[
		'type' => 'button',
		'sub_type' => 'url',
		'index' => 0,
		'parameters' => [
			['type' => 'text', 'text' => '1234'] // token for dynamic URL
		]
	]
];

$service->sendTemplateMessage(
	to: $contact,
	templateName: 'order_followup',
	languageCode: 'es_CO',
	components: $components
);
```

Expected structure sent to the Cloud API:

```json
{
	"messaging_product": "whatsapp",
	"to": "5215512345678",
	"type": "template",
	"template": {
		"name": "order_followup",
		"language": { "code": "es_CO" },
		"components": [
			{ "type": "body", "parameters": [ {"type":"text","text":"Juan"}, {"type":"text","text":"Order #1234"} ] },
			{ "type": "button", "sub_type": "url", "index": 0, "parameters": [ {"type":"text","text":"1234"} ] }
		]
	}
}
```

Valid components: `header`, `body`, `footer`, `button`. See official documentation for advanced parameters (e.g., images in header, quick_reply buttons, etc.).

### Response and Storage

After sending, `wa_message_id` is saved in the corresponding column and the record is persisted in `whatsapp_messages`.

---

## Example: Upload Media and Send an Image

To send an image, first upload it using a `MediaElement`, then use the `media_id` to create the message:

```php
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\MediaElement;
use LaravelWhatsApp\Models\MessageTypes\Image;

$contact = Contact::firstOrCreate(['wa_id' => '5215512345678']);
$from = ApiPhoneNumber::where('phone_number_id', env('WHATSAPP_DEFAULT_API_PHONE_NUMBER_ID'))->first();

// Create media element and upload file
$media = MediaElement::create([
   'api_phone_number_id' => $from->id,
]);

$uploadResponse = $media->upload(storage_path('app/example-image.jpg'));

// Create and send image message using the media ID
$imageMessage = Image::createFromId(
	to: $contact,
	from: $from, 
	mediaId: $media->wa_media_id,
	caption: 'Product photo' // Optional
);

$imageMessage->send();
```

---

## Mark an Incoming Message as Read

```php
use LaravelWhatsApp\Services\WhatsAppMessageService;
use LaravelWhatsApp\Models\WhatsAppMessage;

$service = app(WhatsAppMessageService::class);
$incoming = WhatsAppMessage::find(123); // Previously stored message with direction INCOMING
$service->markAsRead($incoming);
```

---

## Media Download

When an incoming message contains media, a record is created in `whatsapp_media_elements` and the `DownloadMedia` Job can be executed.

```php
use igedeon\LaravelWhatsApp\Jobs\DownloadMedia;
use LaravelWhatsApp\Models\MediaElement;

$media = MediaElement::find(55);
DownloadMedia::dispatch($media); // Uses queue configured in WHATSAPP_MEDIA_DOWNLOAD_QUEUE
```

The download uses the disk configured in `WHATSAPP_DOWNLOAD_DISK` and saves the file with a unique name.

---

## Event: Message Reception (`WhatsAppMessageReceived`)

The package fires an event every time a message is received via webhook:

```php
LaravelWhatsApp\\Events\\WhatsAppMessageReceived
```

Event properties:

- `$message` (`WhatsAppMessage`): The message record.
- `$media` (`MediaElement|null`): Associated media if applicable and already downloaded.
- `$mediaDownloaded` (`bool`): `true` if the event was fired after media download; `false` if the message had no media.

Flow:
1. Incoming message WITHOUT media: event is fired immediately after persisting the message.
2. Incoming message WITH media: `DownloadMedia` is queued. Event is fired ONLY after download completes.

### Default Listener

The package includes a reference listener:

```php
LaravelWhatsApp\\Listeners\\HandleWhatsAppMessageReceived
```

This listener only does `Log::info(...)`. You can replace it by publishing the package config.

Publish config if you haven't already:

```bash
php artisan vendor:publish --provider="LaravelWhatsApp\\WhatsAppServiceProvider" --tag=config
```

In `config/whatsapp.php` you'll find:

```php
'listeners' => [
	'whatsapp_message_received' => \\LaravelWhatsApp\\Listeners\\HandleWhatsAppMessageReceived::class,
],
```

### Use Your Own Listener

Create your class:

```php
namespace App\\Listeners;

use LaravelWhatsApp\\Events\\WhatsAppMessageReceived;

class MyListener
{
	public function handle(WhatsAppMessageReceived $event): void
	{
		if ($event->mediaDownloaded) {
			// Process already downloaded media
		} else {
			// Process message without media
		}
	}
}
```

Edit `config/whatsapp.php`:

```php
'listeners' => [
	'whatsapp_message_received' => App\\Listeners\\MyListener::class,
],
```

### Listener in Queue

```php
use Illuminate\\Contracts\\Queue\\ShouldQueue;
use Illuminate\\Queue\\InteractsWithQueue;

class MyListener implements ShouldQueue
{
	use InteractsWithQueue;

	public function handle(WhatsAppMessageReceived $event): void
	{
		// Heavy task here
	}
}
```

Make sure to configure `QUEUE_CONNECTION` appropriately.

### Multiple Listeners

You can manually register multiple listeners (if you want separate logic) in your `AppServiceProvider`:

```php
use Illuminate\\Support\\Facades\\Event;
use LaravelWhatsApp\\Events\\WhatsAppMessageReceived;

Event::listen(WhatsAppMessageReceived::class, [
	App\\Listeners\\MyListener::class,
	App\\Listeners\\OtherListener::class,
]);
```

---

## Tests

Add tests in `tests/` and run Pest:

```bash
composer test
```

---

## License

MIT

