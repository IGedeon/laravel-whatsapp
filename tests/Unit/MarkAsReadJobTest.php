<?php

use LaravelWhatsApp\Jobs\MarkAsRead;
use LaravelWhatsApp\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('MarkAsRead Job', function () {

    it('marks a message as read', function () {
        $message = WhatsAppMessage::factory()->create();

        Bus::fake();
        MarkAsRead::dispatch($message);
        Bus::assertDispatched(MarkAsRead::class, function ($job) use ($message) {
            return $job->message->id === $message->id;
        });
    });

    
});
