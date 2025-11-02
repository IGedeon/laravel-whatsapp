<?php

use Illuminate\Support\Facades\Route;
use LaravelWhatsApp\Http\Controllers\WebhookController;

Route::get('/whatsapp/webhook', [WebhookController::class, 'verify']);
Route::post('/whatsapp/webhook', [WebhookController::class, 'receive'])->middleware(\LaravelWhatsApp\Http\Middleware\VerifyMetaSignature::class);
