<?php

namespace LaravelWhatsApp\Models\MessageTypes;

use Illuminate\Database\Eloquent\Casts\Attribute;
use LaravelWhatsApp\Enums\MessageDirection;
use LaravelWhatsApp\Enums\MessageType;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\WhatsAppMessage;

class Location extends WhatsAppMessage
{
    /**
     * Estos atributos derivados facilitan acceder a partes del contenido.
     */
    protected $appends = ['latitude', 'longitude', 'name', 'address'];

    public static function create(Contact $to, ApiPhoneNumber $from, float $latitude, float $longitude, string $name = '', string $address = ''): self
    {
        $instance = new self;

        $instance->initMessage(
            MessageType::LOCATION,
            MessageDirection::OUTGOING,
            $to,
            $from,
            [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'name' => $name,
                'address' => $address,
            ]
        );

        return $instance;
    }

    protected function latitude(): Attribute
    {
        return self::makeContentAttribute('latitude', 0.0);
    }

    protected function longitude(): Attribute
    {
        return self::makeContentAttribute('longitude', 0.0);
    }

    protected function name(): Attribute
    {
        return self::makeContentAttribute('name', '');
    }

    protected function address(): Attribute
    {
        return self::makeContentAttribute('address', '');
    }
}
