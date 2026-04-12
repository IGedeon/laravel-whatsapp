<?php

namespace LaravelWhatsApp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use LaravelWhatsApp\Enums\MessageType;

class Template extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'name',
        'message_send_ttl_seconds',
        'parameter_format',
        'components',
        'language',
        'status',
        'category',
        'sub_category',
        'whatsapp_id',
        'business_account_id',
    ];

    protected $casts = [
        'components' => 'array',
    ];

    public function businessAccount(): BelongsTo
    {
        return $this->belongsTo(BusinessAccount::class, 'business_account_id', 'id');
    }

    private function replaceParameters(string $text, array $parameters): string
    {
        foreach ($parameters as $index => $parameter) {
            $placeholder = '{{' . ($index + 1) . '}}';
            
            $value = Arr::get($parameter, 'text', '***N/A***');

            $text = str_replace($placeholder, $value, $text);
        }

        return $text;
    }

    private function getHeaderReplacement(array $templateComponents, array $messageComponents): ?array
    {
        if (! isset($templateComponents['header']) || ! isset($messageComponents['header'])) {
            return null;
        }

        $format = strtolower(Arr::get($templateComponents, 'header.format', ''));

        if ($format === 'text') {
            $headerText = Arr::get($templateComponents, 'header.text', '');
            $messageParameters = Arr::get($messageComponents, 'header.parameters', []);

            return [
                'type' => 'text',
                'text' => $this->replaceParameters($headerText, $messageParameters),
            ];
        }

        if ($format === 'image') {
            return [
                'type' => 'image',
                'image' => Arr::get($messageComponents, 'header.parameters.0.image'),
            ];
        }

        throw new \Exception('Unsupported header format: '.$format);
    }

    private function getBodyReplacement(array $templateComponents, array $messageComponents): string
    {
        if (! isset($templateComponents['body']) || ! isset($messageComponents['body'])) {
            return '';
        }

        $bodyText = Arr::get($templateComponents, 'body.text', '');
        $messageParameters = Arr::get($messageComponents, 'body.parameters', []);

        return $this->replaceParameters($bodyText, $messageParameters);
    }

    public function getReplacements(WhatsAppMessage $message): array
    {
        if ($message->type !== MessageType::TEMPLATE) {
            throw new \InvalidArgumentException('Message must be of type TEMPLATE to get replacements.');
        }

        if($this->name !== $message->getContentProperty('name')) {
            throw new \InvalidArgumentException('Template name does not match message content.');
        }

        if($this->language !== $message->getContentProperty('language.code')) {
            throw new \InvalidArgumentException('Template language does not match message content.');
        }

        $templateComponents = collect($this->components)
            ->keyBy(fn($component) => strtolower(trim($component['type'])))
            ->toArray();

        $messageComponents = collect($message->getContentProperty('components'))
            ->keyBy(fn($component) => strtolower(trim($component['type'])))
            ->toArray();

        return [
            'name' => $this->name,
            'language' => [
                'code' => $this->language
            ],
            'header' => $this->getHeaderReplacement($templateComponents, $messageComponents),
            'body' => $this->getBodyReplacement($templateComponents, $messageComponents),
        ];
    }
}
