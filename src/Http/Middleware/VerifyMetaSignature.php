<?php

namespace LaravelWhatsApp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VerifyMetaSignature
{
    public function handle(Request $request, Closure $next)
    {
        $sigHeader = $request->header('X-Hub-Signature-256');
        $appSecret = config('whatsapp.app_secret'); // .env: WHATSAPP_APP_SECRET

        if(!$appSecret){
            throw new \Exception('App secret not configured in whatsapp.app_secret');
        }

        if (!$sigHeader) {
            return response('Missing signature', 401);
        }

        // Normaliza el header: quita prefijo "sha256=" si viene
        $received = Str::after($sigHeader, 'sha256=');

        // Cuerpo crudo exacto
        $raw = $request->getContent();

        // Calcula HMAC en HEX (no base64), sin alterar el body
        $calc = hash_hmac('sha256', $raw, $appSecret);

        // Comparación constante (evita timing attacks)
        if (!hash_equals($calc, $received)) {
            Log::warning('Invalid webhook signature', [
                'calculated' => $calc,
                'received' => $received,
            ]);
            return response('Invalid signature', 401);
        }

        return $next($request);
    }
}
