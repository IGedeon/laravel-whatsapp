<?php

namespace LaravelWhatsApp\Models\MessageTypes;

use Illuminate\Database\Eloquent\Casts\Attribute;
use LaravelWhatsApp\Enums\MessageDirection;
use LaravelWhatsApp\Enums\MessageType;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\WhatsAppMessage;

class Video extends WhatsAppMessage
{
    protected $appends = ['waId', 'url', 'caption'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public static function createFromUrl(Contact $to, ApiPhoneNumber $from, string $mediaUrl, string $caption = ''): self
    {
        if (trim($mediaUrl) === '') {
            throw new \InvalidArgumentException('media_url cannot be empty');
        }
        $instance = new self;
        $instance->initMessage(
            MessageType::VIDEO,
            MessageDirection::OUTGOING,
            $to,
            $from,
            [
                'url' => $mediaUrl,
                'caption' => $caption,
            ]
        );

        return $instance;
    }

    public static function createFromId(Contact $to, ApiPhoneNumber $from, string $mediaId, string $caption = ''): self
    {
        if (trim($mediaId) === '') {
            throw new \InvalidArgumentException('media_id cannot be empty');
        }
        $instance = new self;
        $instance->initMessage(
            MessageType::VIDEO,
            MessageDirection::OUTGOING,
            $to,
            $from,
            [
                'id' => $mediaId,
                'caption' => $caption,
            ]
        );

        return $instance;
    }

    protected function waId(): Attribute
    {
        return self::makeContentAttribute('id', false);
    }

    protected function url(): Attribute
    {
        return self::makeContentAttribute('url', '');
    }

    protected function caption(): Attribute
    {
        return self::makeContentAttribute('caption', '');
    }
}
