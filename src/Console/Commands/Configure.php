<?php

namespace LaravelWhatsApp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use LaravelWhatsApp\Models\AccessToken;
use LaravelWhatsApp\Models\BusinessAccount;
use LaravelWhatsApp\Models\MetaApp;

class Configure extends Command
{
    protected $signature = 'whatsapp:configure {--access-token=} {--waba-id=} {--app-id=} {--app-secret=} {--verify-token=}';

    protected $description = 'Configure whatsapp resources like Business Account, Meta App and Access Token';

    public function handle(): int
    {

        $service = new \LaravelWhatsApp\Services\WhatsAppService;

        // Valida Token
        $access_token = $this->option('access-token');
        while (empty($access_token)) {
            $access_token = $this->secret('Enter your Access Token (hidden):');
        }

        try {
            $tokenData = $service::apiGetRequest(access_token: $access_token, uri: '/me');
        } catch (\Exception $e) {
            $this->error('Failed to validate token: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info('Access Token is valid for user: '.$tokenData['name']);

        // Valida App
        $appId = $this->option('app-id');
        while (empty($appId)) {
            $appId = $this->ask('Enter your Meta App ID:');
        }

        try {
            $appData = $service::apiGetRequest(
                access_token: $access_token,
                uri: $appId
            );
        } catch (\Exception $e) {
            $this->error('Failed to validate app: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info('App is valid: '.$appData['name']);

        // Valida scopes
        $appSecret = $this->option('app-secret');
        if (empty($appSecret)) {
            // $appSecret = MetaApp::where('meta_app_id', $appId)->first()?->app_secret;
        }
        while (empty($appSecret)) {
            $appSecret = $this->ask('Enter your App Secret:');
        }

        try {
            $scopesData = $service::apiGetRequest(
                access_token: $access_token,
                uri: 'debug_token?input_token='.
                    $access_token.
                    '&access_token='.
                    $appId.
                    '|'.
                    $appSecret
            );
        } catch (\Exception $e) {
            $this->error('Failed to validate token scopes: '.$e->getMessage());

            return self::FAILURE;
        }

        $granular_scopes = Arr::get($scopesData, 'data.scopes');

        $required = [
            'business_management',
            'whatsapp_business_management',
            'whatsapp_business_messaging',
            'whatsapp_business_manage_events',
        ];

        foreach ($required as $scope) {
            if (! in_array($scope, $granular_scopes)) {
                $this->error('Access Token is missing required scope: '.$scope);

                return self::FAILURE;
            }
        }
        $this->info('Access Token has all required scopes.');

        // Valida WABA
        $wabaId = $this->option('waba-id');
        while (empty($wabaId)) {
            $wabaId = $this->ask('Enter your WhatsApp Business Account ID:');
        }

        try {
            $wabaData = $service::apiGetRequest(
                access_token: $access_token,
                uri: "/$wabaId?fields=id,name,currency,timezone_id,message_template_namespace,message_templates,phone_numbers,subscribed_apps"
            );
        } catch (\Exception $e) {
            $this->error('Failed to validate WABA: '.$e->getMessage());

            return self::FAILURE;
        }

        $verifyToken = $this->option('verify-token');
        if (empty($verifyToken)) {
            $verifyToken = $this->ask('Enter your Verify Token (default: verify_token):', 'verify_token');
        }

        $subscribeAction = $service::apiPostRequest(
            access_token: $access_token,
            uri: "/$wabaId/subscribed_apps",
            payload: [
                'override_callback_uri' => config('whatsapp.subscribe_url'),
                'verify_token' => $verifyToken,
            ]
        );

        if (Arr::get($subscribeAction, 'success') === true) {
            $this->info('WABA successfully subscribed to the App.');
        } else {
            $this->error('Failed to subscribe WABA to the App.');

            return self::FAILURE;
        }

        $app = MetaApp::updateOrCreate(
            ['meta_app_id' => $appId],
            [
                'name' => $appData['name'],
                'app_secret' => $appSecret,
                'verify_token' => $verifyToken,
            ]
        );

        $token = AccessToken::updateOrCreate(
            ['access_token' => $access_token],
            [
                'whatsapp_id' => $tokenData['id'],
                'name' => $tokenData['name'],
                'meta_app_id' => $app->id,
                'expires_at' => $scopesData['data']['expires_at'] == 0 ? null : $scopesData['data']['expires_at'],
            ]
        );

        $waba = BusinessAccount::firstOrCreate(
            ['whatsapp_id' => $wabaData['id']]
        );

        $wabaData = $service::apiGetRequest(
            access_token: $access_token,
            uri: "/$wabaId?fields=id,name,currency,timezone_id,message_template_namespace,message_templates,phone_numbers,subscribed_apps"
        );
        $waba->fillFromMeta($wabaData);

        $waba->accessTokens()->syncWithoutDetaching([$token->id]);

        return self::SUCCESS;
    }
}
