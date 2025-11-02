<?php

namespace Database\Factories;

use LaravelWhatsApp\Models\BusinessAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessAccountFactory extends Factory
{
    protected $model = BusinessAccount::class;

    public function definition()
    {
        return [
            'whatsapp_id' => $this->faker->uuid,
            'name' => $this->faker->company,
            'currency' => $this->faker->currencyCode,
            'timezone_id' => $this->faker->timezone,
            'message_template_namespace' => $this->faker->uuid,
            'access_token' => $this->faker->uuid,
            'subscribed_apps' => [],
        ];
    }
}
