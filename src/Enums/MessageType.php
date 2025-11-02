<?php

namespace LaravelWhatsApp\Enums;

enum MessageType: string
{
    case AUDIO = 'audio';
    case BUTTON = 'button';
    case CONTACTS = 'contacts';
    case DOCUMENT = 'document';
    case IMAGE = 'image';
    case INTERACTIVE = 'interactive';
    case LOCATION = 'location';
    case ORDER = 'order';
    case TEMPLATE = 'template';
    case REACTION = 'reaction';
    case STICKER = 'sticker';
    case TEXT = 'text';
    case VIDEO = 'video';

    case ERRORS = 'errors';
    case SYSTEM = 'system';
    case UNSUPPORTED = 'unsupported';
    case GROUP = 'group';

    public function isSupported(): bool
    {
        return match ($this) {
            self::ERRORS,
            self::SYSTEM,
            self::UNSUPPORTED,
            self::GROUP => false,
            default => true,
        };
    }

    public function isMedia(): bool
    {
        return match ($this) {
            self::AUDIO,
            self::DOCUMENT,
            self::IMAGE,
            self::STICKER,
            self::VIDEO => true,
            default => false,
        };
    }
}
