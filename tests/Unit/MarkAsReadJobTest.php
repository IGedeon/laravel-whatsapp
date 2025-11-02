<?php

use Illuminate\Support\Facades\Bus;
use LaravelWhatsApp\Jobs\MarkAsRead;
use LaravelWhatsApp\Models\WhatsAppMessage;

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
