<?php

return [
    // Webhook subscribe URL built from APP_URL. Not exposed directly as env var.
    'subscribe_url' => env('APP_URL').'/whatsapp/webhook',

    // Core API configuration
    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v24.0'),
    'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
    'download_disk' => env('WHATSAPP_DOWNLOAD_DISK', 'local'),

    // Queue configuration (only connection + specific queues currently used)
    'queue' => [
        'connection' => env('WHATSAPP_QUEUE_CONNECTION', 'sync'),
        'media_download_queue' => env('WHATSAPP_MEDIA_DOWNLOAD_QUEUE', 'default'),
        'mark_as_read_queue' => env('WHATSAPP_MARK_AS_READ_QUEUE', 'default'),
    ],

    // Flattened queue keys kept for backward compatibility with earlier Job constructors
    'media_download_queue' => env('WHATSAPP_MEDIA_DOWNLOAD_QUEUE', 'default'),
    'mark_as_read_queue' => env('WHATSAPP_MARK_AS_READ_QUEUE', 'default'),

    // If true, incoming messages are marked as read immediately (dispatches MarkAsRead job)
    'mark_messages_as_read_immediately' => env('WHATSAPP_MARK_MESSAGES_AS_READ_IMMEDIATELY', false),

    // Event listeners (can be overridden in published config)
    'listeners' => [
        'whatsapp_message_received' => \LaravelWhatsApp\Listeners\HandleWhatsAppMessageReceived::class,
    ],
  
    'mark_messages_as_read_immediately' => env('WHATSAPP_MARK_MESSAGES_AS_READ_IMMEDIATELY', false),
    'expire_media_days' => env('WHATSAPP_EXPIRE_MEDIA_DAYS', 15),

    // Allow overriding the Contact and ApiPhoneNumber model classes
    'contact_model' => \LaravelWhatsApp\Models\Contact::class,
    'apiphone_model' => \LaravelWhatsApp\Models\ApiPhoneNumber::class,
];
