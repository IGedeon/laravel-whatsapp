<?php

namespace Database\Factories\Models;

use Illuminate\Support\Str;
// use Database\Factories\MetaAppFactory;
use LaravelWhatsApp\Models\AccessToken;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccessTokenFactory extends Factory
{
    protected $model = AccessToken::class;

    public function definition()
    {
        return [
            // 'meta_app_id' => MetaAppFactory::new(),
            'whatsapp_id' => Str::random(20),
            'name' => $this->faker->word(),
            'access_token' => Str::random(32),
            'expires_at' => $this->faker->dateTimeBetween('+1 day', '+1 year'),
        ];
    }
}
