<?php

use LaravelWhatsApp\Listeners\HandleWhatsAppMessageReceived;
use LaravelWhatsApp\Listeners\HandleWhatsAppMessageStatusChange;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\MediaElement;
use LaravelWhatsApp\Models\WhatsAppMessage;

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
        'whatsapp_message_received' => HandleWhatsAppMessageReceived::class,
        'whatsapp_message_status_change' => HandleWhatsAppMessageStatusChange::class,
    ],

    'mark_messages_as_read_immediately' => env('WHATSAPP_MARK_MESSAGES_AS_READ_IMMEDIATELY', false),
    'expire_media_days' => env('WHATSAPP_EXPIRE_MEDIA_DAYS', 15),

    'log_driver' => env('WHATSAPP_LOG_DRIVER', 'single'),

    // Allow overriding the Contact, ApiPhoneNumber, Message, and MediaElement model classes
    'contact_model' => Contact::class,
    'apiphone_model' => ApiPhoneNumber::class,
    'message_model' => WhatsAppMessage::class,
    'media_model' => MediaElement::class,
];
