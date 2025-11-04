# ![Tests](https://github.com/IGedeon/laravel-whatsapp/actions/workflows/test.yml/badge.svg)
# Laravel WhatsApp Cloud API (Multi App / Multi WABA / Multi Número)

Biblioteca para integrar la WhatsApp Cloud API en aplicaciones Laravel.

## Características

- Múltiples Meta Apps, múltiples Cuentas de Negocio (WABAs), múltiples Access Tokens y múltiples números
- Webhook global centralizado con verificación de firma multi-secreto
- Persistencia de contactos, mensajes, plantillas, medios, access tokens, meta apps y errores
- Carga y descarga de medios (imágenes, documentos, audio, video, stickers)
- Colas opcionales para descarga de medios y marcado como leído
- Comportamiento configurable para marcar mensajes como leídos inmediatamente
- Comando interactivo de configuración (`php artisan whatsapp:configure`) que valida y persiste Meta App, Access Token, suscripción WABA y datos
- Soporte para mensajes de plantilla (aprobadas por Meta)

---

## Capacidades de Business Account, Meta App, Access Token y Plantillas

- **MetaApp**: Almacena `meta_app_id`, `name`, `app_secret`, `verify_token`. Soporta múltiples aplicaciones simultáneamente. La verificación GET del webhook y la validación de la firma POST buscan entre TODAS las apps almacenadas.
- **AccessToken**: Almacena el token de larga duración (`access_token`), metadatos (`whatsapp_id`, `name`, `expires_at`) y lo vincula a una `MetaApp`. Se pueden conservar tokens históricos; el envío usa siempre el último token asociado a la cuenta de negocio.
- **BusinessAccount**: Representa una cuenta de WhatsApp Business (WABA) incluyendo perfil, moneda, zona horaria, namespace de plantillas, apps suscritas, números y plantillas. Los access tokens son ahora una relación muchos-a-muchos vía `whatsapp_business_tokens`.
- **Template**: Plantillas aprobadas (nombre, idioma, categoría, componentes) asociadas a Business Accounts para mensajes templados.
- **Números de teléfono**: Sincronizados en `whatsapp_api_phone_numbers` con nivel de throughput, configuración de webhook, quality rating, etc.

Modelos en `src/Models/MetaApp.php`, `AccessToken.php`, `BusinessAccount.php`, `Template.php` y archivos relacionados. Factories y migraciones incluidas.

---

## Instalación

Instalación y puesta en marcha recomendada (sin pasos de publicación, el paquete expone sus propios assets por defecto):

1. Instala el paquete:
  ```bash
  composer require igedeon/laravel-whatsapp
  ```
2. Ejecuta migraciones (si el proyecto las requiere para tablas locales):
  ```bash
  php artisan migrate
  ```
3. Configura e integra tu primera Meta App / WABA ejecutando el asistente:
  ```bash
  php artisan whatsapp:configure
  ```
  Durante el flujo se solicitarán: Access Token, Meta App ID, App Secret, Verify Token y WABA ID.
  El comando:
  - Valida identidad del token (`/me`)
  - Valida la Meta App
  - Verifica scopes granulares requeridos
  - Verifica y suscribe la WABA usando `subscribe_url` (`APP_URL` + `/whatsapp/webhook`)
  - Persiste `MetaApp`, `AccessToken`, `BusinessAccount` y enlaza vía pivot
4. (Opcional) Repite `whatsapp:configure` para añadir nuevas Apps o rotar tokens.
5. Comienza a enviar mensajes usando los modelos (`Contact`, `ApiPhoneNumber`, `WhatsAppMessage`).

Notas:
- Ya no es necesario publicar manualmente archivos de configuración o migraciones para el flujo básico.
- Si deseas sobreescribir el archivo de configuración, aún puedes usar `php artisan whatsapp:install --force`.
- La URL del webhook se deriva de `APP_URL`; asegúrate de tenerla correcta en `.env` antes de configurar.

---

## Seguridad de Webhook: Firma Multi App y Verificación

`VerifyMetaSignature` carga todos los `app_secret` de `whatsapp_meta_apps` y acepta la petición si CUALQUIER HMAC coincide con `X-Hub-Signature-256`.

La verificación inicial GET (`hub.challenge`) se aprueba si el `hub_verify_token` coincide con cualquier `verify_token` almacenado.

Desactivar middleware en pruebas locales:
```php
$this->withoutMiddleware(\LaravelWhatsApp\Http\Middleware\VerifyMetaSignature::class);
```

Generar firma en tests:
```php
$signature = hash_hmac('sha256', $rawBody, $metaApp->app_secret);
```

Encabezado:
```
X-Hub-Signature-256: sha256=<signature>
```

Si ninguna coincide, se rechaza (401) y se registra en logs.

---

## Extender modelos Contact y ApiPhoneNumber

Puedes extender los modelos `Contact` y `ApiPhoneNumber` para agregar lógica o atributos personalizados. Define la clase del modelo en el archivo de configuración:

```php
// config/whatsapp.php
'contact_model' => \App\Models\CustomContact::class,
'apiphone_model' => \App\Models\CustomApiPhoneNumber::class,
```

Ambos modelos pueden ser sobrescritos para personalizar relaciones, validaciones o métodos. El paquete usará la clase configurada en todos los procesos internos.

Ejemplo de modelo extendido:

```php
namespace App\Models;

use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\ApiPhoneNumber;

class CustomContact extends Contact
{
	// Agrega tus métodos o propiedades aquí
}

class CustomApiPhoneNumber extends ApiPhoneNumber
{
	// Agrega tus métodos o propiedades aquí
}
```

Ejecuta `php artisan config:cache` si utilizas caché.

---

### Opcional: Publicar o forzar (solo si necesitas personalizar)

```bash
php artisan whatsapp:install --force      # Sobrescribe archivos publicados (config/migrations)
php artisan whatsapp:install --no-config  # Evita publicar config
php artisan whatsapp:install --no-migrations
```

Usa estos flags solo si quieres tener copias locales de las migraciones o del config para personalizarlos.

---

## Variables de entorno y configuración (`config/whatsapp.php`)

| Variable | Requerido | Defecto | Descripción |
|----------|-----------|---------|-------------|
| `APP_URL` | Sí | - | Usada para construir `subscribe_url` (`/whatsapp/webhook`). |
| `WHATSAPP_GRAPH_VERSION` | No | `v24.0` | Versión Graph API para llamadas Cloud API. |
| `WHATSAPP_BASE_URL` | No | `https://graph.facebook.com` | Host base de Graph API. |
| `WHATSAPP_DOWNLOAD_DISK` | No | `local` | Disco para archivos descargados. |
| `WHATSAPP_QUEUE_CONNECTION` | No | `sync` | Conexión de cola. |
| `WHATSAPP_MEDIA_DOWNLOAD_QUEUE` | No | `default` | Cola para job `DownloadMedia`. |
| `WHATSAPP_MARK_AS_READ_QUEUE` | No | `default` | Cola para job `MarkAsRead`. |
| `WHATSAPP_MARK_MESSAGES_AS_READ_IMMEDIATELY` | No | `false` | Si `true`, marca mensajes como leídos inmediatamente. |


Notas:
1. App secrets y verify tokens ahora viven en BD (`whatsapp_meta_apps`), no en env.
2. Access tokens viven en BD (`whatsapp_access_tokens`). Rotar vía `whatsapp:configure`.
3. Para enviar mensajes necesitas un `ApiPhoneNumber` real y una Business Account con al menos un Access Token.
4. Si sólo hay un número, puede seleccionarse automáticamente.

Ejemplo mínimo `.env`:
```dotenv
APP_URL="https://example.com"
WHATSAPP_DOWNLOAD_DISK=public
```

---

## Envío de mensajes (`WhatsAppMessage`)

Flujo básico:

1. Crea (o recupera) el destinatario `Contact` (campo `wa_id` = número sin +, con código de país).
2. Crea o recupera el `ApiPhoneNumber` desde el que envías (si no tienes uno por defecto configurado).
3. Instancia `WhatsAppMessage`, inicialízalo con `initMessage()` pasando el tipo y el contenido.
4. Llama a `send()` en el modelo (delegado al `WhatsAppMessageService`).

Tipos soportados (enum `MessageType`): `text`, `image`, `video`, `audio`, `document`, `sticker`, `location`, `contacts`, `button`, `interactive`, `reaction`, `order`, `template`.

Estructura genérica de `content`:

```php
[
  // Para texto
  'body' => 'Mensaje de prueba',
  // Para imagen/video/documento/audio (tras subir el medio) usa 'id' del medio
  // 'id' => 'MEDIA_ID'
]
```

Payload enviado automáticamente:

```json
{
  "messaging_product": "whatsapp",
  "to": "<wa_id>",
  "type": "text|image|...",
  "text|image|video|...": { ... content ... }
}
```

---

### Ejemplo: Enviar mensaje de texto

```php
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\WhatsAppMessage;
use LaravelWhatsApp\Enums\MessageType;

// 1. Obtener o crear contacto
$contact = Contact::firstOrCreate([
	'wa_id' => '5215512345678', // Número destino sin '+'
], [
	'nombre' => 'Juan Perez'
]);

// 2. (Opcional) Obtener número de envío si no hay uno por defecto
$from = ApiPhoneNumber::where('phone_number_id', env('WHATSAPP_DEFAULT_API_PHONE_NUMBER_ID'))->first();

// 3. Crear instancia de mensaje
$message = new WhatsAppMessage();
$message->initMessage(
	type: MessageType::TEXT,
	to: $contact,
	from: $from, // Puede omitirse si hay uno por defecto
	contentProps: [
		'body' => '¡Hola! Este es un mensaje de prueba.'
	]
);

// 4. Enviar
$message->send();
```

### Ejemplo: Enviar mensaje de plantilla

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
			['type' => 'text', 'text' => 'Pedido #1234'],
		]
	],
	// ...
];

$service->sendTemplateMessage(
	to: $contact,
	name: 'order_confirmation',
	language: ['code' => 'es_MX'],
	components: $components
);
```

---

Estructura esperada en API:
```json
{
  "messaging_product": "whatsapp",
  "to": "5215512345678",
  "type": "template",
  "template": {
    "name": "order_followup",
    "language": { "code": "es_CO" },
    "components": []
  }
}
```

Componentes válidos: `header`, `body`, `footer`, `button`.

---

## Subir media y enviar imagen
```php
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\MediaElement;
use LaravelWhatsApp\Models\MessageTypes\Image;

$contact = Contact::firstOrCreate(['wa_id' => '5215512345678']);
$from = ApiPhoneNumber::first();

$media = MediaElement::create([
  'api_phone_number_id' => $from->id,
]);

$media->upload(storage_path('app/example-image.jpg'));

$imageMessage = Image::createFromId(
  to: $contact,
  from: $from,
  mediaId: $media->wa_media_id,
  caption: 'Foto de producto'
);

$imageMessage->send();
```

---

## Marcar mensaje entrante como leído
```php
use LaravelWhatsApp\Services\WhatsAppMessageService;
use LaravelWhatsApp\Models\WhatsAppMessage;

$service = app(WhatsAppMessageService::class);
$incoming = WhatsAppMessage::find(123);
$service->markAsRead($incoming);
```

---

## Job de descarga de media
```php
use LaravelWhatsApp\Jobs\DownloadMedia;
use LaravelWhatsApp\Models\MediaElement;

$media = MediaElement::find(55);
DownloadMedia::dispatch($media); // cola desde config
```

---

## Evento: `WhatsAppMessageReceived`

Se dispara por cada mensaje entrante. Publica config para sobrescribir listener.
```php
'listeners' => [
  'whatsapp_message_received' => \LaravelWhatsApp\Listeners\HandleWhatsAppMessageReceived::class,
];
```

Implementa tu listener para lógica personalizada (queue, heavy processing, etc.).

---

## Tests
Ejecutar Pest:
```bash
composer test
```

---

## Licencia
MIT
