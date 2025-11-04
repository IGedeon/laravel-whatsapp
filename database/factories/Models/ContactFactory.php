<?php

namespace Database\Factories\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use LaravelWhatsApp\Models\Contact;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition()
    {
        return [
            'wa_id' => $this->faker->numerify('52155#######'),
            'name' => $this->faker->name,
        ];
    }
}
