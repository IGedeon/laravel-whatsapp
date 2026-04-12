<?php

namespace LaravelWhatsApp\Models\MessageTypes;

use Illuminate\Database\Eloquent\Casts\Attribute;
use LaravelWhatsApp\Enums\MessageDirection;
use LaravelWhatsApp\Enums\MessageType;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\WhatsAppMessage;

class Reaction extends WhatsAppMessage
{
    /**
     * Estos atributos derivados facilitan acceder a partes del contenido.
     */
    protected $appends = ['emoji'];

    public static function create(Contact $to, ApiPhoneNumber $from, string $wappMessageId, string $emoji): self
    {
        if (trim($wappMessageId) === '') {
            throw new \InvalidArgumentException('Message ID cannot be empty');
        }

        $instance = new self;
        $instance->initMessage(
            MessageType::REACTION,
            MessageDirection::OUTGOING,
            $to,
            $from,
            [
                'message_id' => $wappMessageId,
                'emoji' => $emoji,
            ]
        );

        return $instance;
    }

    protected function emoji(): Attribute
    {
        return self::makeContentAttribute('emoji', '');
    }
}
