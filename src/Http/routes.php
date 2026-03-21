<?php

use Illuminate\Support\Facades\Route;
use LaravelWhatsApp\Http\Controllers\WebhookController;
use LaravelWhatsApp\Http\Middleware\VerifyMetaSignature;

Route::get('/whatsapp/webhook', [WebhookController::class, 'verify']);
Route::post('/whatsapp/webhook', [WebhookController::class, 'receive'])->middleware(VerifyMetaSignature::class);
