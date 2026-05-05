<?php

namespace LaravelWhatsApp\Models\MessageTypes;

use Illuminate\Database\Eloquent\Casts\Attribute;
use LaravelWhatsApp\Enums\MessageDirection;
use LaravelWhatsApp\Enums\MessageType;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\WhatsAppMessage;

class Document extends WhatsAppMessage
{
    protected $appends = ['waId', 'link', 'caption', 'filename'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public static function createFromUrl(Contact $to, ApiPhoneNumber $from, string $mediaUrl, string $caption = '', string $filename = ''): self
    {
        if (trim($mediaUrl) === '') {
            throw new \InvalidArgumentException('media_url cannot be empty');
        }
        $instance = new self;
        $instance->initMessage(
            MessageType::DOCUMENT,
            MessageDirection::OUTGOING,
            $to,
            $from,
            [
                'link' => $mediaUrl,
                'caption' => $caption,
                'filename' => $filename,
            ]
        );

        return $instance;
    }

    public static function createFromId(Contact $to, ApiPhoneNumber $from, string $mediaId, string $caption = '', string $filename = ''): self
    {
        if (trim($mediaId) === '') {
            throw new \InvalidArgumentException('media_id cannot be empty');
        }
        $instance = new self;
        $instance->initMessage(
            MessageType::DOCUMENT,
            MessageDirection::OUTGOING,
            $to,
            $from,
            [
                'id' => $mediaId,
                'caption' => $caption,
                'filename' => $filename,
            ]
        );

        return $instance;
    }

    protected function waId(): Attribute
    {
        return self::makeContentAttribute('id', false);
    }

    protected function link(): Attribute
    {
        return self::makeContentAttribute('link', '');
    }

    protected function caption(): Attribute
    {
        return self::makeContentAttribute('caption', '');
    }

    protected function filename(): Attribute
    {
        return self::makeContentAttribute('filename', '');
    }
}
