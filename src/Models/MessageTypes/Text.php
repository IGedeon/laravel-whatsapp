<?php

namespace LaravelWhatsApp\Models\MessageTypes;


use Illuminate\Support\Arr;
use LaravelWhatsApp\Enums\MessageType;
use LaravelWhatsApp\Enums\MessageDirection;
use LaravelWhatsApp\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\Contact;

class Text extends WhatsAppMessage
{
    protected $appends = ['body','previewUrl'];

    public function __construct(?Contact $to = null, string $body = '', bool $preview_url = false, ?ApiPhoneNumber $from = null)
    {
        parent::__construct();
        if ($to === null && $from === null && $body === '' && $preview_url === false) {
            // Eloquent empty constructor
            return;
        }
        if ($to === null) {
            throw new \InvalidArgumentException("Contact 'to' must be provided.");
        }
        if (!$from) {
            $class = config("whatsapp.apiphone_model");
            $from = $class::getDefault();
        }
        $this->initMessage(MessageType::TEXT, MessageDirection::OUTGOING, $to, $from, []);
        $this->setContentProperty('body', $body);
        $this->setContentProperty('preview_url', $preview_url);
    }

    public static function create(Contact $to, ApiPhoneNumber $from, string $body, bool $previewUrl = false): self
    {
        $instance = new self($to, $body, $previewUrl, $from);
        return $instance;
    }


    protected function previewUrl(): Attribute
    {
        return self::makeContentAttribute('preview_url', false);
    }

    protected function body(): Attribute
    {
        return self::makeContentAttribute('body', '');
    }

    public function text(): Attribute
    {
        return self::makeContentAttribute('body', '');
    }

    
}