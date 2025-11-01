<?php
namespace Database\Factories;

use LaravelWhatsApp\Models\ApiPhoneNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApiPhoneNumberFactory extends Factory
{
    protected $model = ApiPhoneNumber::class;

    public function definition()
    {
        return [
            'phone_number_id' => $this->faker->numerify('123456789012345'),
            'display_phone_number' => $this->faker->phoneNumber,
        ];
            }
        }
