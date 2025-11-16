<?php

namespace LaravelWhatsApp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Arr;
use LaravelWhatsApp\Enums\MessageDirection;
use LaravelWhatsApp\Enums\MessageStatus;
use LaravelWhatsApp\Enums\MessageType;
use LaravelWhatsApp\Events\WhatsAppMessageStatusChange;
use LaravelWhatsApp\Models\MessageTypes\Image;
use LaravelWhatsApp\Models\MessageTypes\Text;
use LaravelWhatsApp\Services\WhatsAppService;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'contact_id',
        'api_phone_number_id',
        'direction',
        'wa_message_id',
        'timestamp',
        'type',
        'content',
        'status',
        'status_timestamp',
        'conversation_id',
        'pricing_billable',
        'pricing_model',
        'pricing_type',
        'pricing_category',
        'context',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'type' => MessageType::class,
        'billable' => 'boolean',
        'direction' => MessageDirection::class,
        'status_timestamp' => 'datetime',
        'context' => 'array',
        'content' => 'array',
        'status' => MessageStatus::class,
    ];

    public function getContentAttribute($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return $decoded ?: [];
        }

        return [];
    }

    public function contact()
    {
        return $this->belongsTo(config('whatsapp.contact_model'), 'contact_id');
    }

    public function apiPhoneNumber()
    {
        return $this->belongsTo(config('whatsapp.apiphone_model'), 'api_phone_number_id');
    }

    /**
     * Generalized constructor for WhatsAppMessage children
     */
    public function initMessage(
        MessageType $type,
        ?MessageDirection $direction = null,
        ?Contact $to = null,
        ?ApiPhoneNumber $from = null,
        array $contentProps = []
    ): void {
        $this->type = $type;
        $this->direction = $direction ?? MessageDirection::OUTGOING;

        if (! $to) {
            throw new \InvalidArgumentException("Contact 'to' must be provided.");
        }
        $this->contact_id = $to->id;

        if (! $from) {
            $class = config('whatsapp.apiphone_model');
            $from = $class::getDefault();
        }

        $this->api_phone_number_id = $from->id;

        foreach ($contentProps as $key => $value) {
            $this->setContentProperty($key, $value);
        }
    }

    /**
     * Generalized static factory for children
     */
    public static function make(...$args): static
    {
        return new static(...$args);
    }

    /**
     * Helper for Attribute getter
     */
    // Renamed from contentAttribute to makeContentAttribute to avoid Laravel treating it as a mutator/accessor for 'content'
    public static function makeContentAttribute(?string $key = null, $default = null): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        // If Laravel introspects mutators and invokes without arguments, return a trivial attribute
        if ($key === null) {
            return \Illuminate\Database\Eloquent\Casts\Attribute::make(
                get: fn ($value) => $value,
            );
        }

        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value, array $attributes) use ($key, $default) {
                $raw = $attributes['content'] ?? [];
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $raw = $decoded;
                    } else {
                        $raw = [];
                    }
                }

                return Arr::get(is_array($raw) ? $raw : [], $key, $default);
            },
        );
    }

    public function errors()
    {
        return $this->hasMany(WhatsAppMessageError::class, 'message_id', 'id');
    }

    public function getContentProperty($key)
    {
        $content = $this->content;
        if (! $content) {
            return null;
        }
        if (is_string($content)) {
            $content = json_decode($content, true);
        }

        return Arr::get($content, $key);
    }

    public function setContentProperty($key, $value)
    {
        $content = $this->content ?? [];
        if (is_string($content)) {
            $content = json_decode($content, true) ?: [];
        }
        Arr::set($content, $key, $value);
        $this->attributes['content'] = json_encode($content);

        return $this;
    }

    public function send(): self
    {
        if ($this->direction !== MessageDirection::OUTGOING) {
            throw new \Exception('Only outgoing messages can be sent.');
        }

        $this->save();

        // Send the message using the WhatsApp API
        $service = new WhatsAppService;
        $service->send($this);

        return $this->refresh();
    }

    public function markAsRead(bool $typingIndicator = false)
    {
        $service = new WhatsAppService;

        return $service->markAsRead($this, $typingIndicator);
    }

    public function media(): MorphOne
    {
        return $this->morphOne(MediaElement::class, 'mediable');
    }

    // Get Text or Image class based on type
    public function getTypedMessageInstance()
    {
        $contactClass = config('whatsapp.contact_model');
        $apiPhoneClass = config('whatsapp.apiphone_model');

        return match ($this->type) {
            MessageType::TEXT => new Text(
                to: $contactClass::find($this->contact_id),
                body: $this->getContentProperty('body'),
                preview_url: $this->getContentProperty('preview_url') ?? false,
                from: $apiPhoneClass::find($this->api_phone_number_id)
            ),
            MessageType::IMAGE => Image::createFromId(
                to: $contactClass::find($this->contact_id),
                from: $apiPhoneClass::find($this->api_phone_number_id),
                mediaId: $this->getContentProperty('id') ?? '',
                caption: $this->getContentProperty('caption') ?? ''
            ),
            // Add other message types as needed
            default => throw new \Exception("Unsupported message type: {$this->type}"),
        };
    }

    public function changeStatus(MessageStatus $newStatus): self
    {
        $oldStatus = $this->status;
        $this->status = $newStatus;

        if ($oldStatus !== $newStatus) {
            $this->status_timestamp = now();
            $this->save();
            WhatsAppMessageStatusChange::dispatch($this, $newStatus, $oldStatus);

            return $this;
        }

        $this->save();

        return $this;
    }
}
