<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LaravelWhatsApp\Models\ApiPhoneNumber;

class ApiPhoneNumberFactory extends Factory
{
    protected $model = ApiPhoneNumber::class;

    public function definition()
    {
        return [
            'business_account_id' => BusinessAccountFactory::new(),
            'verified_name' => $this->faker->company,
            'code_verification_status' => $this->faker->randomElement(['verified', 'unverified']),
            'display_phone_number' => $this->faker->phoneNumber,
            'quality_rating' => $this->faker->randomElement(['green', 'yellow', 'red']),
            'throughput_level' => $this->faker->randomElement(['low', 'medium', 'high']),
            'webhook_configuration_application' => [],
            'whatsapp_id' => $this->faker->uuid,
        ];
    }
}
