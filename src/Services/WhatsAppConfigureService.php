<?php

namespace LaravelWhatsApp\Services;

use Illuminate\Support\Arr;
use LaravelWhatsApp\Models\AccessToken;
use LaravelWhatsApp\Models\BusinessAccount;
use LaravelWhatsApp\Models\MetaApp;

class WhatsAppConfigureService
{
    private WhatsAppService $whatsAppService;

    public function __construct(
        private readonly string $accessToken,
        private readonly string $wabaId,
        private readonly string $appId,
        private readonly string $appSecret,
        private readonly string $verifyToken,
    ) {
        $service = app(WhatsAppService::class);

        if (! $service) {
            $service = new WhatsAppService;
        }

        $this->whatsAppService = $service;
    }

    public function configure(): array
    {
        try {
            $tokenData = $this->whatsAppService::apiGetRequest(access_token: $this->accessToken, uri: '/me');
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to validate token: '.$e->getMessage(),
            ];
        }

        try {
            $appData = $this->whatsAppService::apiGetRequest(
                access_token: $this->accessToken,
                uri: $this->appId
            );
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to validate app: '.$e->getMessage(),
            ];
        }

        try {
            $scopesData = $this->whatsAppService::apiGetRequest(
                access_token: $this->accessToken,
                uri: 'debug_token?input_token='.
                    $this->accessToken.
                    '&access_token='.
                    $this->appId.
                    '|'.
                    $this->appSecret
            );
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to validate token scopes: '.$e->getMessage(),
            ];
        }

        $granular_scopes = Arr::get($scopesData, 'data.scopes');

        $required = [
            'business_management',
            'whatsapp_business_management',
            'whatsapp_business_messaging',
            'whatsapp_business_manage_events',
        ];

        $scopeError = null;

        foreach ($required as $scope) {
            if (! in_array($scope, $granular_scopes)) {
                $scopeError = 'Access Token is missing required scope: '.$scope;
                break;
            }
        }

        if ($scopeError) {
            return [
                'success' => false,
                'message' => $scopeError,
            ];
        }

        try {
            $wabaData = $this->whatsAppService::apiGetRequest(
                access_token: $this->accessToken,
                uri: "/{$this->wabaId}?fields=id,name,currency,timezone_id,message_template_namespace,message_templates,phone_numbers,subscribed_apps"
            );
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to validate WABA: '.$e->getMessage(),
            ];
        }

        $subscribeAction = $this->whatsAppService::apiPostRequest(
            access_token: $this->accessToken,
            uri: "/{$this->wabaId}/subscribed_apps",
            payload: [
                'override_callback_uri' => config('whatsapp.subscribe_url'),
                'verify_token' => $this->verifyToken,
            ]
        );

        if (Arr::get($subscribeAction, 'success') === true) {
            $app = MetaApp::updateOrCreate(
                ['meta_app_id' => $this->appId],
                [
                    'name' => $appData['name'],
                    'app_secret' => $this->appSecret,
                    'verify_token' => $this->verifyToken,
                ]
            );

            $token = AccessToken::updateOrCreate(
                ['access_token' => $this->accessToken],
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

            $wabaData = $this->whatsAppService::apiGetRequest(
                access_token: $this->accessToken,
                uri: "/{$this->wabaId}?fields=id,name,currency,timezone_id,message_template_namespace,message_templates,phone_numbers,subscribed_apps"
            );

            $fillData = $waba->fillFromMeta($wabaData, returnSelf: false);

            $waba->accessTokens()->syncWithoutDetaching([$token->id]);

            return [
                'success' => true,
                'message' => 'WABA successfully subscribed to the App.',
                'data' => $fillData,
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to subscribe WABA to the App.',
        ];
    }
}
