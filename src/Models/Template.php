<?php

namespace LaravelWhatsApp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use LaravelWhatsApp\Enums\MessageType;
use LaravelWhatsApp\Enums\TemplateParameterFormat;

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
        'parameter_format' => TemplateParameterFormat::class,
    ];

    public function businessAccount(): BelongsTo
    {
        return $this->belongsTo(BusinessAccount::class, 'business_account_id', 'id');
    }

    private function replaceParameters(string $text, array $parameters): string
    {
        foreach ($parameters as $index => $parameter) {
            if($this->parameter_format === TemplateParameterFormat::POSITIONAL){
                $placeholder = '{{'.($index + 1).'}}';
            } else {
                $placeholder = '{{'.Arr::get($parameter, 'parameter_name').'}}';
            }

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

        $format = strtolower(Arr::get($templateComponents, 'header.0.format', ''));

        if ($format === 'text') {
            $headerText = Arr::get($templateComponents, 'header.0.text', '');
            $messageParameters = Arr::get($messageComponents, 'header.0.parameters', []);

            return [
                'type' => 'text',
                'text' => $this->replaceParameters($headerText, $messageParameters),
            ];
        }

        if ($format === 'image') {
            return [
                'type' => 'image',
                'image' => Arr::get($messageComponents, 'header.0.parameters.0.image'),
            ];
        }

        if($format === 'document') {
            return [
                'type' => 'document',
                'document' => Arr::get($messageComponents, 'header.0.parameters.0.document'),
            ];
        }

        throw new \Exception('Unsupported header format: '.$format);
    }

    private function getBodyReplacement(array $templateComponents, array $messageComponents): string
    {
        if (! isset($templateComponents['body']) || ! isset($messageComponents['body'])) {
            return '';
        }

        $bodyText = Arr::get($templateComponents, 'body.0.text', '');
        $messageParameters = Arr::get($messageComponents, 'body.0.parameters', []);

        return $this->replaceParameters($bodyText, $messageParameters);
    }

    private function getFooterReplacement(array $templateComponents): ?string
    {
        if (! isset($templateComponents['footer'])) {
            return null;
        }

        return Arr::get($templateComponents, 'footer.0.text', '');
    }

    private function getButtonsReplacements(array $templateComponents, array $messageComponents): array
    {
        if (! isset($templateComponents['buttons']) || ! isset($messageComponents['button'])) {
            return [];
        }

        $templateButtons = Arr::get($templateComponents, 'buttons.0.buttons', []);
        $messageButtons = Arr::get($messageComponents, 'button', []);

        $replacements = [];

        foreach ($templateButtons as $index => $templateButton) {
            $messageButton = Arr::get($messageButtons, $index);
            $buttonType = strtolower(Arr::get($templateButton, 'type', ''));

            if($buttonType === 'quick_reply') {
                $replacements[] = [
                    'type' => 'quick_reply',
                    'text' => Arr::get($templateButton, 'text'),
                    'payload' => Arr::get($messageButton, 'parameters.0.payload'),
                ];

                continue;
            }

            if($buttonType === 'url') {
                $url = Arr::get($templateButton, 'url');
                $isDinamicUrl = str_contains($url, '{{1}}');

                if($isDinamicUrl) {
                    $url = str_replace('{{1}}', Arr::get($messageButton, 'parameters.0.text', ''), $url);
                }

                $replacements[] = [
                    'type' => 'url',
                    'text' => Arr::get($templateButton, 'text'),
                    'url' => $url,
                ];
                
                continue;
            }

            if($buttonType === 'phone_number') {
                $replacements[] = [
                    'type' => 'phone_number',
                    'text' => Arr::get($templateButton, 'text'),
                    'phone_number' => Arr::get($templateButton, 'phone_number'),
                ];
                
                continue;
            }

            throw new \Exception('Unsupported button type: '.$buttonType);
        }

        return $replacements;
    }

    public function getReplacements(WhatsAppMessage $message): array
    {
        if ($message->type !== MessageType::TEMPLATE) {
            throw new \InvalidArgumentException('Message must be of type TEMPLATE to get replacements.');
        }

        if ($this->name !== $message->getContentProperty('name')) {
            throw new \InvalidArgumentException('Template name does not match message content.');
        }

        if ($this->language !== $message->getContentProperty('language.code')) {
            throw new \InvalidArgumentException('Template language does not match message content.');
        }

        $templateComponents = collect($this->components)
            ->groupBy(fn ($component) => strtolower(trim($component['type'])))
            ->toArray();

        $messageComponents = collect($message->getContentProperty('components'))
            ->groupBy(fn ($component) => strtolower(trim($component['type'])))
            ->toArray();

        return [
            'name' => $this->name,
            'language' => [
                'code' => $this->language,
            ],
            'header' => $this->getHeaderReplacement($templateComponents, $messageComponents),
            'body' => $this->getBodyReplacement($templateComponents, $messageComponents),
            'footer' => $this->getFooterReplacement($templateComponents),
            'buttons' => $this->getButtonsReplacements($templateComponents, $messageComponents),
        ];
    }
}
