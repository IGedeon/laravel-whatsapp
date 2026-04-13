<?php

namespace LaravelWhatsApp\Services;

use Illuminate\Support\Arr;
use LaravelWhatsApp\Models\AccessToken;
use LaravelWhatsApp\Models\BusinessAccount;
use LaravelWhatsApp\Models\MetaApp;

class WhatsAppConfigureService
{
    private WhatsAppService $whatsAppService;
    private string $successMessage = 'WABA successfully subscribed to the App.';

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

    public function setSuccessMessage(string $message): void
    {
        $this->successMessage = $message;
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

        $app = MetaApp::updateOrCreate(
            ['meta_app_id' => $this->appId],
            [
                'name' => $appData['name'],
                'app_secret' => $this->appSecret,
                'verify_token' => $this->verifyToken,
            ]
        );

        $appAccessToken = $this->appId.'|'.$this->appSecret;

        $webhookAction = $this->whatsAppService::apiPostRequest(
            access_token: $appAccessToken,
            uri: "/{$this->appId}/subscriptions",
            payload: [
                'object' => 'whatsapp_business_account',
                'callback_url' => config('whatsapp.subscribe_url'),
                'verify_token' => $this->verifyToken,
                'fields' => 'messages',
                'include_values' => true,
            ]
        );

        if (Arr::get($webhookAction, 'success') !== true) {
            if ($app->wasRecentlyCreated) {
                $app->delete();
            } else {
                $app->update([
                    'name' => $app->getOriginal('name'),
                    'app_secret' => $app->getOriginal('app_secret'),
                    'verify_token' => $app->getOriginal('verify_token'),
                ]);
            }

            return [
                'success' => false,
                'message' => 'Failed to configure webhook on the App.',
                'error' => $webhookAction,
            ];
        }

        $subscribeAction = $this->whatsAppService::apiPostRequest(
            access_token: $this->accessToken,
            uri: "/{$this->wabaId}/subscribed_apps",
            payload: []
        );

        if (Arr::get($subscribeAction, 'success') !== true) {
            if ($app->wasRecentlyCreated) {
                $app->delete();
            } else {
                $app->update([
                    'name' => $app->getOriginal('name'),
                    'app_secret' => $app->getOriginal('app_secret'),
                    'verify_token' => $app->getOriginal('verify_token'),
                ]);
            }

            return [
                'success' => false,
                'message' => 'Failed to subscribe WABA to the App.',
                'error' => $subscribeAction,
            ];
        }

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

        $phoneNumbers = Arr::get($wabaData, 'phone_numbers.data', []);
        $registeredPhones = [];

        foreach ($phoneNumbers as $phone) {
            $phoneId = Arr::get($phone, 'id');

            if (! $phoneId) {
                continue;
            }

            $phoneData = $this->whatsAppService::apiGetRequest(
                access_token: $this->accessToken,
                uri: "/{$phoneId}?fields=id,display_phone_number,status"
            );

            if (Arr::get($phoneData, 'status') === 'CONNECTED') {
                $registeredPhones[] = ['id' => $phoneId, 'number' => Arr::get($phoneData, 'display_phone_number'), 'status' => 'already_connected'];

                continue;
            }

            $registerResult = $this->whatsAppService::apiPostRequest(
                access_token: $this->accessToken,
                uri: "/{$phoneId}/register",
                payload: ['messaging_product' => 'whatsapp', 'pin' => '000000']
            );

            $registeredPhones[] = [
                'id' => $phoneId,
                'number' => Arr::get($phoneData, 'display_phone_number'),
                'status' => Arr::get($registerResult, 'success') ? 'registered' : 'failed',
            ];
        }

        return [
            'success' => true,
            'message' => $this->successMessage,
            'data' => $fillData,
            'phone_numbers' => $registeredPhones,
        ];

    }
}
