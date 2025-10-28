# Laravel WhatsApp Cloud API (Multi Número)

Librería para integrar la WhatsApp Cloud API en aplicaciones Laravel. Incluye:

- Soporte para múltiples números (Business Phone Numbers)
- Persistencia de contactos, mensajes, conversaciones y errores
- Descarga y subida de medios (imágenes, documentos, audio, video, stickers)
- Manejo de colas opcional para envío y descarga de media
- Utilidades para marcar mensajes como leídos

---

## Instalación

```bash
composer require igedeon/laravel-whatsapp
```

Publicar configuración y migraciones (método manual):

```bash
php artisan vendor:publish --provider="LaravelWhatsApp\\WhatsAppServiceProvider" --tag=whatsapp-config
php artisan vendor:publish --provider="LaravelWhatsApp\\WhatsAppServiceProvider" --tag=whatsapp-migrations
php artisan migrate
```

O publicar todo en un solo paso:

```bash
php artisan vendor:publish --provider="LaravelWhatsApp\\WhatsAppServiceProvider" --tag=whatsapp
```

### Comando de instalación rápido

El paquete provee un comando para simplificar la instalación:

```bash
php artisan whatsapp:install            # Publica config y migraciones
php artisan whatsapp:install --migrate  # Publica y ejecuta migraciones
php artisan whatsapp:install --force    # Fuerza sobreescritura de archivos ya publicados
```

Flags disponibles:

- `--force`: sobreescribe archivos ya existentes.
- `--no-config`: no publica el archivo de configuración.
- `--no-migrations`: no publica las migraciones.
- `--migrate`: ejecuta las migraciones inmediatamente.

Ejemplos avanzados:

```bash
php artisan whatsapp:install --no-config --migrate
php artisan whatsapp:install --no-migrations
```

---

## Variables de entorno y configuración (`config/whatsapp.php`)

| Variable | Requerido | Default | Descripción |
|----------|----------|---------|-------------|
| `WHATSAPP_ACCESS_TOKEN` | Sí | - | Token de acceso (Long-Lived) generado en Meta para autenticar peticiones a la Cloud API. Sin esto no se pueden enviar ni descargar medios. |
| `WHATSAPP_VERIFY_TOKEN` | Opcional (solo Webhook) | - | Token que valida el webhook de verificación de Meta. Úsalo si expones endpoint de verificación. |
| `WHATSAPP_APP_SECRET` | Opcional | - | App Secret para validación de firmas de seguridad del webhook. |
| `WHATSAPP_GRAPH_VERSION` | No | `v21.0` | Versión del Graph API usada para construir las URLs de la Cloud API. Actualiza cuando Meta publique nuevas features. |
| `WHATSAPP_BASE_URL` | No | `https://graph.facebook.com` | Host base del Graph API. Modificar solo para entornos de prueba o mocks. |
| `WHATSAPP_DOWNLOAD_DISK` | No | `local` | Disk Laravel (config `filesystems.php`) donde se guardarán los archivos descargados. Ej: `public`, `s3`. |
| `WHATSAPP_QUEUE_CONNECTION` | No | `sync` | Conexión de cola (config `queue.php`). Ej: `redis`, `database`, `sqs`. |
| `WHATSAPP_MEDIA_DOWNLOAD_QUEUE` | No | `default` | Nombre de la cola donde se encola el Job `DownloadMedia`. |
| `WHATSAPP_MARK_AS_READ_QUEUE` | No | `default` | Nombre de la cola donde se encola el Job `MarkAsRead`. |
| `WHATSAPP_DEFAULT_API_PHONE_NUMBER_ID` | Opcional | `null` | ID del número de teléfono Business (phone_number_id) que se usará por defecto al crear mensajes si no se pasa explícitamente un `ApiPhoneNumber`. |

Notas:

1. Para mensajes salientes se requiere al menos un registro en la tabla `whatsapp_api_phone_numbers` (ver migración) con su `phone_number_id` real obtenido del panel de Meta.
2. Si defines `WHATSAPP_DEFAULT_API_PHONE_NUMBER_ID`, el modelo `WhatsAppMessage` intentará usarlo automáticamente cuando llames a `initMessage()` sin pasar el número.
3. Asegura que tu `WHATSAPP_ACCESS_TOKEN` sea de larga duración y actualizado antes de caducar.

Ejemplo `.env` mínimo:

```dotenv
WHATSAPP_ACCESS_TOKEN=EAABxxxxxxxxxxxxxxxxxxxxx
WHATSAPP_DEFAULT_API_PHONE_NUMBER_ID=123456789012345
WHATSAPP_GRAPH_VERSION=v21.0
WHATSAPP_DOWNLOAD_DISK=public
```

---

## Modelo principal para envío: `WhatsAppMessage`

Flujo básico para enviar un mensaje:

1. Crear (o recuperar) el `Contact` destinatario (campo `wa_id` = número sin +, con código país).
2. Crear o recuperar el `ApiPhoneNumber` desde el cual envías (si no tienes default configurado).
3. Instanciar `WhatsAppMessage`, inicializarlo con `initMessage()` pasando tipo y contenido.
4. Llamar a `send()` del modelo (que delega en `WhatsAppMessageService`).

Tipos soportados (enum `MessageType`): `text`, `image`, `video`, `audio`, `document`, `sticker`, `location`, `contacts`, `button`, `interactive`, `reaction`, `order`. Algunos requieren estructura específica en `content`.
\n+Nuevo tipo soportado: `template` para mensajes de plantilla aprobados por Meta. Usa un payload con `template => [ name, language[code], components[] ]`.

Estructura genérica del `content` enviada al API:

```php
[
  // Para texto
  'body' => 'Mensaje de prueba',
  // Para imagen/video/document/audio (después de subir media) usar 'id' del media
  // 'id' => 'MEDIA_ID'
]
```

El servicio construye automáticamente el payload con:

```json
{
  "messaging_product": "whatsapp",
  "to": "<wa_id>",
  "type": "text|image|...",
  "text|image|video|...": { ... contenido ... }
}
```

---

## Ejemplo: Enviar mensaje de texto

```php
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\WhatsAppMessage;
use LaravelWhatsApp\Enums\MessageType;

// 1. Obtener/crear contacto
$contact = Contact::firstOrCreate([
	'wa_id' => '5215512345678', // Número destino sin '+'
], [
	'name' => 'Juan Perez'
]);

// 2. (Opcional) Obtener número de envío si no hay default
$from = ApiPhoneNumber::where('phone_number_id', env('WHATSAPP_DEFAULT_API_PHONE_NUMBER_ID'))->first();

// 3. Crear instancia de mensaje
$message = new WhatsAppMessage();
$message->initMessage(
	type: MessageType::TEXT,
	to: $contact,
	from: $from, // Puede omitirse si hay default configurado
	contentProps: [
		'body' => 'Hola! Este es un mensaje de prueba.'
	]
);

// 4. Enviar
$message->send();
```

## Ejemplo: Enviar mensaje de plantilla (Template Message)

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
	[
		'type' => 'button',
		'sub_type' => 'url',
		'index' => 0,
		'parameters' => [
			['type' => 'text', 'text' => '1234'] // token para URL dinámica
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

Estructura esperada final enviada a la Cloud API:

```json
{
	"messaging_product": "whatsapp",
	"to": "5215512345678",
	"type": "template",
	"template": {
		"name": "order_followup",
		"language": { "code": "es_CO" },
		"components": [
			{ "type": "body", "parameters": [ {"type":"text","text":"Juan"}, {"type":"text","text":"Pedido #1234"} ] },
			{ "type": "button", "sub_type": "url", "index": 0, "parameters": [ {"type":"text","text":"1234"} ] }
		]
	}
}
```

Componentes válidos: `header`, `body`, `footer`, `button`. Consulta la documentación oficial para parámetros avanzados (ej: imágenes en header, quick_reply buttons, etc.).

### Respuesta y almacenamiento

Tras enviarse, se guarda `wa_message_id` en la columna correspondiente y el registro queda persistido en `whatsapp_messages`.

---

## Ejemplo: Subir media y enviar una imagen

Para enviar una imagen primero debes subirla usando un `MediaElement` asociado al número remitente.

```php
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\WhatsAppMessage;
use LaravelWhatsApp\Models\MediaElement;
use LaravelWhatsApp\Enums\MessageType;

$contact = Contact::firstOrCreate(['wa_id' => '5215512345678']);
$from = ApiPhoneNumber::where('phone_number_id', env('WHATSAPP_DEFAULT_API_PHONE_NUMBER_ID'))->first();

// Crear registro de media (vacío inicial)
$media = MediaElement::create([
   'api_phone_number_id' => $from->id,
]);

// Subir el archivo local (ruta absoluta o relativa dentro del proyecto)
$uploadResponse = $media->upload(storage_path('app/example-image.jpg'));

// El método upload actualiza $media->wa_media_id

// Construir y enviar mensaje de imagen usando el ID devuelto
$imageMessage = new WhatsAppMessage();
$imageMessage->initMessage(
	type: MessageType::IMAGE,
	to: $contact,
	from: $from,
	contentProps: [
		'id' => $media->wa_media_id, // Cloud API espera { image: { id: '...' } }
		// Opcional: 'caption' => 'Foto de producto'
	]
);
$imageMessage->send();
```

---

## Marcar un mensaje entrante como leído

```php
use LaravelWhatsApp\Services\WhatsAppMessageService;
use LaravelWhatsApp\Models\WhatsAppMessage;

$service = app(WhatsAppMessageService::class);
$incoming = WhatsAppMessage::find(123); // Mensaje previamente almacenado con direction INCOMING
$service->markAsRead($incoming);
```

---

## Descarga de Media

Cuando un mensaje entrante trae media, se crea registro en `whatsapp_media_elements` y puede ejecutarse el Job `DownloadMedia`.

```php
use igedeon\LaravelWhatsApp\Jobs\DownloadMedia;
use LaravelWhatsApp\Models\MediaElement;

$media = MediaElement::find(55);
DownloadMedia::dispatch($media); // Usa cola configurada en WHATSAPP_MEDIA_DOWNLOAD_QUEUE
```

La descarga usa el disk configurado en `WHATSAPP_DOWNLOAD_DISK` y guarda el archivo con un nombre único.

---

## Evento: Recepción de Mensajes (`WhatsAppMessageReceived`)

El paquete dispara un evento cada vez que se recibe un mensaje vía webhook:

```php
LaravelWhatsApp\\Events\\WhatsAppMessageReceived
```

Propiedades del evento:

- `$message` (`WhatsAppMessage`): El registro del mensaje.
- `$media` (`MediaElement|null`): Media asociada si aplica y ya está descargada.
- `$mediaDownloaded` (`bool`): `true` si el evento se disparó después de descargar la media; `false` si el mensaje no tenía media.

Flujo:
1. Mensaje entrante SIN media: el evento se dispara inmediatamente tras persistir el mensaje.
2. Mensaje entrante CON media: se encola `DownloadMedia`. El evento se dispara SOLO después de completar la descarga.

### Listener por defecto

El paquete incluye un listener de referencia:

```php
LaravelWhatsApp\\Listeners\\HandleWhatsAppMessageReceived
```

Este listener solo hace `Log::info(...)`. Puedes reemplazarlo publicando la configuración del paquete.

Publicar config si aún no lo hiciste:

```bash
php artisan vendor:publish --provider="LaravelWhatsApp\\WhatsAppServiceProvider" --tag=config
```

En `config/whatsapp.php` encontrarás:

```php
'listeners' => [
	'whatsapp_message_received' => \\LaravelWhatsApp\\Listeners\\HandleWhatsAppMessageReceived::class,
],
```

### Usar tu propio listener

Crea tu clase:

```php
namespace App\\Listeners;

use LaravelWhatsApp\\Events\\WhatsAppMessageReceived;

class MiListener
{
	public function handle(WhatsAppMessageReceived $event): void
	{
		if ($event->mediaDownloaded) {
			// Procesar media ya descargada
		} else {
			// Procesar mensaje sin media
		}
	}
}
```

Edita `config/whatsapp.php`:

```php
'listeners' => [
	'whatsapp_message_received' => App\\Listeners\\MiListener::class,
],
```

### Listener en cola

```php
use Illuminate\\Contracts\\Queue\\ShouldQueue;
use Illuminate\\Queue\\InteractsWithQueue;

class MiListener implements ShouldQueue
{
	use InteractsWithQueue;

	public function handle(WhatsAppMessageReceived $event): void
	{
		// Tarea pesada aquí
	}
}
```

Asegúrate de configurar `QUEUE_CONNECTION` apropiadamente.

### Múltiples listeners

Puedes registrar múltiples listeners manualmente (si quieres lógica separada) en tu `AppServiceProvider`:

```php
use Illuminate\\Support\\Facades\\Event;
use LaravelWhatsApp\\Events\\WhatsAppMessageReceived;

Event::listen(WhatsAppMessageReceived::class, [
	App\\Listeners\\MiListener::class,
	App\\Listeners\\OtroListener::class,
]);
```

---

## Tests

Agrega tests en `tests/` y ejecuta Pest:

```bash
composer test
```

---

## Licencia

MIT

