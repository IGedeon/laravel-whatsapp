# CLAUDE.md

## Project Overview

**igedeon/laravel-whatsapp** — Laravel package for integrating Meta's WhatsApp Cloud API with multi-app, multi-WABA (WhatsApp Business Account), multi-number support.

- **Author:** Carlos Trujillo (ctrujillo@sideso.com.co)
- **License:** MIT
- **GitHub:** IGedeon/laravel-whatsapp
- **Branch principal:** master

## Tech Stack

- **Language:** PHP 8.2+
- **Framework:** Laravel 12 (illuminate/support, illuminate/http, illuminate/database)
- **HTTP Client:** Guzzle 7.8
- **API:** Meta WhatsApp Cloud API (Graph API v24.0)
- **Testing:** Pest PHP 3.8 + Orchestra Testbench
- **Linting:** Laravel Pint
- **Static Analysis:** PHPStan
- **Refactoring:** Rector
- **CI/CD:** GitHub Actions (test + lint workflows)

## Common Commands

```bash
# Tests
composer test                    # Run all tests (Pest)
composer test:coverage           # Run with coverage
vendor/bin/pest tests/Feature    # Feature tests only
vendor/bin/pest tests/Unit       # Unit tests only

# Code quality
pint                             # Format code (Laravel Pint)
pint --test                      # Check formatting (dry run)
vendor/bin/phpstan analyze       # Static analysis
vendor/bin/rector process        # Automated refactoring

# Package installation (from consumer app)
php artisan whatsapp:install --migrate
php artisan whatsapp:configure
```

## Project Structure

```
src/
├── Config/whatsapp.php                   # Package configuration
├── Console/Commands/
│   ├── Configure.php                     # whatsapp:configure command
│   └── WhatsAppInstall.php               # whatsapp:install command
├── Enums/                                # MessageType, MessageStatus, MessageDirection, MimeType
├── Events/                               # WhatsAppMessageReceived, WhatsAppMessageStatusChange
├── Http/
│   ├── Controllers/WebhookController.php # Webhook verify + receive
│   ├── Middleware/VerifyMetaSignature.php # HMAC-SHA256 signature validation
│   └── routes.php                        # GET/POST /whatsapp/webhook
├── Jobs/                                 # DownloadMedia, MarkAsRead
├── Listeners/                            # Default event listeners (logging)
├── Models/
│   ├── WhatsAppMessage.php               # Core message model
│   ├── Contact.php                       # WhatsApp contacts
│   ├── BusinessAccount.php               # WABA accounts
│   ├── ApiPhoneNumber.php                # Phone numbers
│   ├── MetaApp.php                       # Meta app credentials
│   ├── AccessToken.php                   # OAuth tokens
│   ├── Template.php                      # Message templates
│   ├── MediaElement.php                  # Media files (polymorphic)
│   ├── WhatsAppMessageError.php          # Error logging
│   └── MessageTypes/                     # Text, Image, Template subtypes
├── Services/
│   ├── WhatsAppService.php               # High-level API (send, markAsRead, API calls)
│   └── WhatsAppConfigureService.php      # Setup/validation service
└── WhatsAppServiceProvider.php           # Package service provider
database/
├── factories/Models/                     # Model factories for testing
└── migrations/                           # 10 migration files (whatsapp_* tables)
tests/
├── Feature/                              # WebhookTest, MediaWorkflowTest
├── Unit/                                 # Model, job, enum tests
├── TestCase.php                          # Base: SQLite in-memory, auto-migrations
└── Pest.php                              # Pest config with RefreshDatabase
stubs/                                    # JSON webhook payload fixtures for tests
```

## Architecture

### Patterns

- **Service Provider Pattern:** `WhatsAppServiceProvider` registers singleton services, config, routes, commands, event listeners, and migrations.
- **Service Layer:** `WhatsAppService` (sending, API calls) and `WhatsAppConfigureService` (setup).
- **Event-Driven:** `WhatsAppMessageReceived` and `WhatsAppMessageStatusChange` events with configurable listeners.
- **Queue Jobs:** `DownloadMedia` and `MarkAsRead` run on configurable queues.
- **Middleware:** `VerifyMetaSignature` validates HMAC-SHA256 on webhooks.
- **Factory Methods:** Message type classes (`Text`, `Image`, `Template`) have static `create()` methods.

### Message Flow

- **Incoming:** Webhook POST → Signature verification → Contact upsert → Message creation → (optional media download) → Event dispatch
- **Outgoing:** Create message → `WhatsAppService::send()` → Graph API POST → Status update → Error recording on failure
- **Status updates:** Webhook status → `changeStatus()` → `WhatsAppMessageStatusChange` event

### Database Tables (all prefixed `whatsapp_`)

`business_accounts`, `api_phone_numbers`, `contacts`, `messages`, `media_elements`, `message_errors`, `templates`, `meta_apps`, `access_tokens`, `business_tokens` (pivot)

### Security

- Webhook HMAC-SHA256 signature verification (supports multiple app secrets)
- App secrets and tokens stored in database, not in .env
- Bearer token authentication for Meta API calls

## Coding Conventions

- **Naming:** PascalCase classes, camelCase methods/properties, snake_case DB columns/tables
- **Enums:** PHP 8.1+ backed enums with UPPERCASE values
- **Eloquent:** `$fillable` arrays, `$casts` for type safety, relation methods, query builder for findOrCreate
- **PHP features:** Constructor promotion, readonly properties, type hints on all parameters and returns
- **Table prefix:** All database tables use `whatsapp_` prefix
- **Language:** Code in English, commit messages and docs may be in Spanish
- **Formatting:** Laravel Pint defaults (auto-committed by CI on push)

## CI/CD

- **test.yml:** Runs on push/PR to master — PHP 8.4, `composer install`, `vendor/bin/pest`
- **lint.yml:** Runs on all pushes — Laravel Pint, auto-commits formatted code

## Configuration

Key config options in `src/Config/whatsapp.php` (overridable via env):

| Env Variable | Default | Purpose |
|---|---|---|
| `WHATSAPP_GRAPH_VERSION` | `v24.0` | Meta Graph API version |
| `WHATSAPP_BASE_URL` | `https://graph.facebook.com` | Graph API base URL |
| `WHATSAPP_DOWNLOAD_DISK` | `local` | Storage disk for media |
| `WHATSAPP_QUEUE_CONNECTION` | `sync` | Queue connection |
| `WHATSAPP_MARK_MESSAGES_AS_READ_IMMEDIATELY` | `false` | Auto-mark incoming as read |
| `WHATSAPP_EXPIRE_MEDIA_DAYS` | `15` | Media expiration in days |

Models `Contact` and `ApiPhoneNumber` are overridable via config (`contact_model`, `apiphone_model`).

## Testing

- **Framework:** Pest PHP with Orchestra Testbench
- **Database:** SQLite in-memory with `RefreshDatabase` trait
- **Fixtures:** JSON webhook payloads in `stubs/`
- **Factories:** All core models have factories in `database/factories/Models/`
- Tests must pass before merging to master (enforced by CI)
