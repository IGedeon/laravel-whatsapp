<?php
namespace Database\Factories;

use LaravelWhatsApp\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

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

