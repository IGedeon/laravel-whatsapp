<?php

return [
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v24.0'),
    'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
    'download_disk' => env('WHATSAPP_DOWNLOAD_DISK', 'local'),
    'queue' => [
        'enabled' => env('WHATSAPP_QUEUE', false),
        'connection' => env('WHATSAPP_QUEUE_CONNECTION', 'sync'),
        'media_download_queue' => env('WHATSAPP_MEDIA_DOWNLOAD_QUEUE', 'default'),
        'mark_as_read_queue' => env('WHATSAPP_MARK_AS_READ_QUEUE', 'default'),
    ],
    'default_display_phone_number' => env('WHATSAPP_DEFAULT_DISPLAY_PHONE_NUMBER', null),
    'listeners' => [
        // Clase listener para el evento WhatsAppMessageReceived. Puede ser override en config published.
        'whatsapp_message_received' => \LaravelWhatsApp\Listeners\HandleWhatsAppMessageReceived::class,
    ],
    'mark_messages_as_read_immediately' => env('WHATSAPP_MARK_MESSAGES_AS_READ_IMMEDIATELY', false),
    'expire_media_days' => env('WHATSAPP_EXPIRE_MEDIA_DAYS', 15),

    // Allow overriding the Contact and ApiPhoneNumber model classes
    'contact_model' => \LaravelWhatsApp\Models\Contact::class,
    'apiphone_model' => \LaravelWhatsApp\Models\ApiPhoneNumber::class,
];
