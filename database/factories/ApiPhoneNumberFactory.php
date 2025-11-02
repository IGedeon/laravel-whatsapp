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
            'name' => $this->faker->company,
            'display_phone_number' => $this->faker->phoneNumber,
            'access_token' => $this->faker->uuid,
            'phone_number_id' => $this->faker->numerify('123456789012345'),
        ];
    }
}
