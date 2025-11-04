<?php

namespace Database\Factories\Models;

use LaravelWhatsApp\Models\MetaApp;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MetaAppFactory extends Factory
{
    protected $model = MetaApp::class;

    public function definition()
    {
        return [
            'meta_app_id' => Str::random(20),
            'name' => $this->faker->company(),
            'app_secret' => Str::random(32),
            'verify_token' => Str::random(16),
        ];
    }
}
