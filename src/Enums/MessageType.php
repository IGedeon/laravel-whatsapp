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

    public static function notSupported(bool $returnValues = false): array
    {
        $cases = [
            self::ERRORS,
            self::SYSTEM,
            self::UNSUPPORTED,
            self::GROUP,
        ];

        if ($returnValues) {
            return array_map(fn($case) => $case->value, $cases);
        }

        return $cases;
    }

    public function isSupported(): bool
    {
        return !in_array($this->value, self::notSupported(true));
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
