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
    protected $appends = ['waId'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public static function createFromId(Contact $to, ApiPhoneNumber $from, string $mediaId): self
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
            ]
        );

        return $instance;
    }

    protected function waId(): Attribute
    {
        return self::makeContentAttribute('id', false);
    }
}
