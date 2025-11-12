<?php

namespace LaravelWhatsApp\Enums;

enum MessageStatus: string
{
    case SENDING = 'sending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case READ = 'read';
    case FAILED = 'failed';
}
