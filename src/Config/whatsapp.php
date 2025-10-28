<?php

return [
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),
    'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
    'download_disk' => env('WHATSAPP_DOWNLOAD_DISK', 'local'),
    'queue' => [
        'enabled' => env('WHATSAPP_QUEUE', false),
        'connection' => env('WHATSAPP_QUEUE_CONNECTION', 'sync'),
        'media_download_queue' => env('WHATSAPP_MEDIA_DOWNLOAD_QUEUE', 'default'),
        'mark_as_read_queue' => env('WHATSAPP_MARK_AS_READ_QUEUE', 'default'),
    ],
    'default_api_phone_number_id' => env('WHATSAPP_DEFAULT_API_PHONE_NUMBER_ID', null),
    'listeners' => [
        // Clase listener para el evento WhatsAppMessageReceived. Puede ser override en config published.
        'whatsapp_message_received' => \LaravelWhatsApp\Listeners\HandleWhatsAppMessageReceived::class,
    ],
    'mark_messages_as_read_immediately' => env('WHATSAPP_MARK_MESSAGES_AS_READ_IMMEDIATELY', false),
];
