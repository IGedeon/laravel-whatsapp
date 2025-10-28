<?php

namespace LaravelWhatsApp\Enums;

enum MessageDirection: string
{
    case INCOMING = 'incoming';
    case OUTGOING = 'outgoing';
}

