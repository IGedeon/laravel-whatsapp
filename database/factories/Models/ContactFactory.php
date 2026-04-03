<?php

namespace Database\Factories\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\Contact;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition()
    {
        return [
            'api_phone_id' => ApiPhoneNumber::factory(),
            'wa_id' => $this->faker->numerify('52155#######'),
            'user_id' => null,
            'username' => null,
            'name' => $this->faker->name,
        ];
    }

    public function withBsuid(): static
    {
        return $this->state(fn () => [
            'user_id' => 'CO.'.$this->faker->numerify('####################'),
        ]);
    }

    public function bsuidOnly(): static
    {
        return $this->state(fn () => [
            'wa_id' => null,
            'user_id' => 'CO.'.$this->faker->numerify('####################'),
            'username' => '@'.$this->faker->userName,
        ]);
    }
}
