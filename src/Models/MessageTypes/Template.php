<?php

namespace LaravelWhatsApp\Models\MessageTypes;

use Illuminate\Database\Eloquent\Casts\Attribute;
use LaravelWhatsApp\Enums\MessageDirection;
use LaravelWhatsApp\Enums\MessageType;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\WhatsAppMessage;

/**
 * Representa un mensaje de plantilla (template message) de la Cloud API.
 * La estructura esperada en el payload para templates es:
 * {
 *   "messaging_product": "whatsapp",
 *   "to": "<wa_id>",
 *   "type": "template",
 *   "template": {
 *       "name": "<template_name>",
 *       "language": { "code": "<language_code>" },
 *       "components": [ { ... } ]
 *   }
 * }
 *
 * Components puede incluir tipos: header, body, footer, button.
 * Cada componente tiene a su vez parámetros según la documentación oficial.
 */
class Template extends WhatsAppMessage
{
    /**
     * Estos atributos derivados facilitan acceder a partes del contenido.
     */
    protected $appends = ['name','languageCode','components'];

    /**
     * Crea una instancia lista para ser enviada.
     *
     * @param Contact $to Contacto destino (modelo interno con wa_id)
     * @param ApiPhoneNumber $from Número API desde el cual se envía
     * @param string $name Nombre de la plantilla registrada en Meta
     * @param string $languageCode Código de idioma (ej: "es_MX", "en_US")
     * @param array $components Array de componentes conforme a la API
     */
    public static function create(Contact $to, ApiPhoneNumber $from, string $name, string $languageCode, array $components = []): self
    {
        if(trim($name) === '') {
            throw new \InvalidArgumentException('Template name cannot be empty');
        }
        if(trim($languageCode) === '') {
            throw new \InvalidArgumentException('Template language code cannot be empty');
        }

        $instance = new self();
        $instance->initMessage(
            MessageType::TEMPLATE,
            MessageDirection::OUTGOING,
            $to,
            $from,
            [
                'name' => $name,
                'language' => [ 'code' => $languageCode ],
                'components' => $components,
            ]
        );
        return $instance;
    }

    protected function name(): Attribute
    {
        return self::makeContentAttribute('name', '');
    }

    protected function languageCode(): Attribute
    {
        // La Cloud API coloca language como array con key code
        return Attribute::make(
            get: function () {
                $language = $this->content['language'] ?? [];
                return $language['code'] ?? '';
            },
        );
    }

    protected function components(): Attribute
    {
        return self::makeContentAttribute('components', []);
    }
}
