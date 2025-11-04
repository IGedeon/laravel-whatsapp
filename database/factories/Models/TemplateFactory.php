<?php

namespace Database\Factories\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use LaravelWhatsApp\Models\Template;

class TemplateFactory extends Factory
{
    protected $model = Template::class;

    public function definition()
    {

        return [
            'name' => $this->faker->word,
            'message_send_ttl_seconds' => $this->faker->numberBetween(3600, 86400),
            'parameter_format' => $this->faker->randomElement(['text', 'media']),
            'components' => [],
            'language' => $this->faker->locale,
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'category' => $this->faker->word,
            'sub_category' => $this->faker->word,
            'whatsapp_id' => $this->faker->uuid,
            'business_account_id' => BusinessAccountFactory::new(),
        ];
    }
}
