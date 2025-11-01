<?php

namespace Database\Factories;

use LaravelWhatsApp\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use LaravelWhatsApp\Enums\MessageDirection;
use LaravelWhatsApp\Enums\MessageStatus;

class WhatsAppMessageFactory extends Factory
{
    protected $model = WhatsAppMessage::class;

    public function definition()
    {
        
        return [
            'direction' => MessageDirection::INCOMING,
            'status' => MessageStatus::READ,
            'content' => ['body' => $this->faker->sentence],
            'wa_message_id' => $this->faker->uuid,
            'api_phone_number_id' => ApiPhoneNumberFactory::new(),
            'contact_id' => ContactFactory::new(),
        ];
    }
}
