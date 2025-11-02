<?php

use LaravelWhatsApp\Models\ApiPhoneNumber;
use LaravelWhatsApp\Models\Contact;
use LaravelWhatsApp\Models\MessageTypes\Template;

it('builds a template message payload structure', function () {
    // Usar instancias in-memory sin conexión a BD
    $contact = new Contact([
        'wa_id' => '5215550001111',
        'name' => 'Contacto Test',
    ]);
    $apiPhone = new ApiPhoneNumber([
        'label' => 'default',
        'phone_number_id' => '1234567890',
    ]);

    $components = [
        [
            'type' => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => 'Juan'],
                ['type' => 'text', 'text' => 'Pedido #1234'],
            ],
        ],
    ];

    $message = Template::create($contact, $apiPhone, 'order_followup', 'es_MX', $components);

    expect($message->type->value)->toBe('template');
    expect($message->content['name'])->toBe('order_followup');
    expect($message->content['language']['code'])->toBe('es_MX');
    expect($message->content['components'][0]['type'])->toBe('body');
});
