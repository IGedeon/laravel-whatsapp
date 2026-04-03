## igedeon/laravel-whatsapp

Laravel package for integrating Meta's WhatsApp Cloud API with support for multiple Meta Apps, Business Accounts (WABAs), Access Tokens, and Phone Numbers.

### Installation & Setup

After installing the package, run migrations and configure your first Meta App:

```bash
php artisan whatsapp:install --migrate
php artisan whatsapp:configure
```

`whatsapp:configure` is an interactive wizard that validates credentials and creates the necessary database records (MetaApp, AccessToken, BusinessAccount).

### Architecture

All configuration (app secrets, access tokens, WABA IDs) is stored in the database — not in `.env`. The key models are:

- `MetaApp` — Meta App credentials (`meta_app_id`, `app_secret`, `verify_token`)
- `AccessToken` — OAuth token linked to a MetaApp and one or more BusinessAccounts
- `BusinessAccount` — WhatsApp Business Account (WABA)
- `ApiPhoneNumber` — Phone number linked to a BusinessAccount
- `Contact` — WhatsApp user identified by `user_id` (BSUID) or `wa_id` (phone number)
- `WhatsAppMessage` — Sent and received messages

### Sending Messages

Always retrieve a `Contact` and an `ApiPhoneNumber` before sending:

```php
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\MessageTypes\Text;
use LaravelWhatsApp\Models\MessageTypes\Image;
use LaravelWhatsApp\Models\MessageTypes\Template;

$from = ApiPhoneNumber::first(); // or find by whatsapp_id

$contact = Contact::firstOrCreate(
    ['api_phone_id' => $from->id, 'wa_id' => '5215512345678'],
    ['name' => 'Juan Perez']
);

// Text message
$message = Text::create($contact, $from, 'Hello!');
$message->send();

// Image message (from URL)
$message = Image::createFromUrl($contact, $from, 'https://example.com/img.jpg', 'Caption');
$message->send();
```

### Sending Template Messages

```php
use LaravelWhatsApp\Services\WhatsAppService;

$service = new WhatsAppService;
$service->sendTemplateMessage(
    to: $contact,
    templateName: 'hello_world',
    languageCode: 'en_US',
    components: []
);
```

### Receiving Messages (Webhooks)

Webhook routes are auto-registered:

- `GET /whatsapp/webhook` — Meta verification endpoint
- `POST /whatsapp/webhook` — Incoming messages and status updates (protected by HMAC-SHA256 middleware)

Listen to incoming messages via events:

```php
use LaravelWhatsApp\Events\WhatsAppMessageReceived;
use LaravelWhatsApp\Events\WhatsAppMessageStatusChange;

// In EventServiceProvider or using a Listener class:
Event::listen(WhatsAppMessageReceived::class, function ($event) {
    $message = $event->message;   // WhatsAppMessage model
    $media   = $event->media;     // MediaElement|null
    $downloaded = $event->mediaDownloaded; // bool
});

Event::listen(WhatsAppMessageStatusChange::class, function ($event) {
    $message = $event->message; // WhatsAppMessage with updated status
});
```

Custom listeners can also be registered via config:

```php
// config/whatsapp.php
'listeners' => [
    'whatsapp_message_received'       => \App\Listeners\HandleIncomingMessage::class,
    'whatsapp_message_status_change'  => \App\Listeners\HandleStatusChange::class,
],
```

### Contact Identification (BSUID)

From March 2026, WhatsApp includes a Business-Scoped User ID (`user_id` / BSUID) in all webhooks. When a user enables a WhatsApp username, `wa_id` (phone) may be absent. Always prefer `user_id` for contact lookup:

```php
// The package handles this automatically in WebhookController.
// When creating contacts manually, prefer user_id when available:
$contact = Contact::firstOrCreate(
    ['api_phone_id' => $from->id, 'user_id' => 'US.13491208655302741918'],
    ['wa_id' => '5215512345678', 'name' => 'Juan']
);
```

### Message Types

Supported `MessageType` enum values: `TEXT`, `IMAGE`, `VIDEO`, `AUDIO`, `DOCUMENT`, `STICKER`, `TEMPLATE`, `LOCATION`, `CONTACTS`, `BUTTON`, `INTERACTIVE`, `REACTION`, `ORDER`, `ERRORS`, `SYSTEM`, `UNSUPPORTED`, `GROUP`.

### Message Statuses

`MessageStatus` enum: `SENDING` → `SENT` → `DELIVERED` → `READ` / `FAILED`.

### Media Handling

Incoming media is downloaded asynchronously via the `DownloadMedia` job. Configure the queue and storage disk:

```php
// config/whatsapp.php
'download_disk' => env('WHATSAPP_DOWNLOAD_DISK', 'local'),
'queue' => [
    'connection'           => env('WHATSAPP_QUEUE_CONNECTION', 'sync'),
    'media_download_queue' => env('WHATSAPP_MEDIA_DOWNLOAD_QUEUE', 'default'),
    'mark_as_read_queue'   => env('WHATSAPP_MARK_AS_READ_QUEUE', 'default'),
],
```

### Key Configuration Options

```php
// config/whatsapp.php
'graph_version'                        => env('WHATSAPP_GRAPH_VERSION', 'v24.0'),
'mark_messages_as_read_immediately'    => env('WHATSAPP_MARK_MESSAGES_AS_READ_IMMEDIATELY', false),
'expire_media_days'                    => env('WHATSAPP_EXPIRE_MEDIA_DAYS', 15),
'contact_model'                        => \LaravelWhatsApp\Models\Contact::class,
'apiphone_model'                       => \LaravelWhatsApp\Models\ApiPhoneNumber::class,
```

### Best Practices

- Never store Meta credentials in `.env`; use `whatsapp:configure` to persist them in the database.
- Use the queue for media-heavy workflows (`WHATSAPP_QUEUE_CONNECTION=redis`).
- Override `contact_model` or `apiphone_model` only when you need to extend the base Eloquent models.
- Always check `$message->send()` return value (`bool`) to detect API failures; errors are stored in `WhatsAppMessageError`.
- For multi-WABA setups, scope contacts by `api_phone_id` to avoid cross-account collisions.
- Use `user_id` (BSUID) as the primary contact identifier to support users who enable the WhatsApp username feature.
