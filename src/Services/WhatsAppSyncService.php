<?php

namespace LaravelWhatsApp\Services;

use Illuminate\Support\Arr;
use LaravelWhatsApp\Models\BusinessAccount;
use LaravelWhatsApp\Models\MetaApp;

class WhatsAppSyncService
{
    private BusinessAccount $businessAccount;

    private WhatsAppConfigureService $whatsAppConfigureService;

    public function __construct(
        private readonly string $wabaId,
    ) {
        $this->businessAccount = BusinessAccount::with([
            'accessTokens',
        ])
            ->where('whatsapp_id', $this->wabaId)
            ->firstOrFail();

        $subscribedApps = Arr::get($this->businessAccount->subscribed_apps, 'data', []);

        $appIds = collect($subscribedApps)
            ->dot()
            ->filter(fn ($value, $key) => str_ends_with($key, 'whatsapp_business_api_data.id'))
            ->values()
            ->toArray();

        if (empty($appIds)) {
            throw new \Exception('No subscribed Meta Apps found for this Business Account. Please ensure you have subscribed your app to the Business Account and try again.');
        }

        $app = MetaApp::whereIn('meta_app_id', $appIds)->first();

        if (! $app) {
            throw new \Exception('No matching Meta App found in the database for the subscribed apps. Please ensure your Meta App is created and subscribed to the Business Account, then run the sync command again.');
        }

        $this->whatsAppConfigureService = new WhatsAppConfigureService(
            accessToken: $this->businessAccount->latestAccessToken() ?? '',
            wabaId: $this->wabaId,
            appId: $app->meta_app_id,
            appSecret: $app->app_secret,
            verifyToken: $app->verify_token,
        );

        $this->whatsAppConfigureService->setSuccessMessage('Business Account and Meta App successfully synchronized.');
    }

    public function sync(): array
    {
        return $this->whatsAppConfigureService->configure();
    }
}
