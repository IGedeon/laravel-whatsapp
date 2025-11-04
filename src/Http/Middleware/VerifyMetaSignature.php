<?php

namespace LaravelWhatsApp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LaravelWhatsApp\Models\MetaApp;

class VerifyMetaSignature
{
    public function handle(Request $request, Closure $next)
    {
        $sigHeader = $request->header('X-Hub-Signature-256');

        if (! $sigHeader) {
            return response('Missing signature', 401);
        }

        // Normaliza el header: quita prefijo "sha256=" si viene
        $received = Str::after($sigHeader, 'sha256=');

        // Cuerpo crudo exacto
        $raw = $request->getContent();

        $appSecrets = MetaApp::pluck('app_secret')->toArray();

        $calcHashes = array_map(function ($appSecret) use ($raw) {
            return hash_hmac('sha256', $raw, $appSecret);
        }, $appSecrets);

        // Verifica si alguno coincide
        $calc = null;
        foreach ($calcHashes as $hash) {
            if (hash_equals($hash, $received)) {
                $calc = $hash;
                break;
            }
        }

        if (is_null($calc)) {
            Log::warning('Invalid webhook signature', [
                'calculated' => $calc,
                'received' => $received,
            ]);

            return response('Invalid signature', 401);
        }

        return $next($request);
    }
}
