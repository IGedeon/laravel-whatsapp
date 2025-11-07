<?php

namespace LaravelWhatsApp\Console\Commands;

use Illuminate\Console\Command;
use LaravelWhatsApp\Services\WhatsAppConfigureService;

class Configure extends Command
{
    protected $signature = 'whatsapp:configure {--access-token=} {--waba-id=} {--app-id=} {--app-secret=} {--verify-token=}';

    protected $description = 'Configure whatsapp resources like Business Account, Meta App and Access Token';

    public function handle(): int
    {
        $accessToken = $this->option('access-token');

        while (empty($accessToken)) {
            $accessToken = $this->secret('Enter your Access Token (hidden):');
        }

        $appId = $this->option('app-id');

        while (empty($appId)) {
            $appId = $this->ask('Enter your Meta App ID:');
        }

        $appSecret = $this->option('app-secret');

        while (empty($appSecret)) {
            $appSecret = $this->secret('Enter your App Secret (hidden):');
        }

        $wabaId = $this->option('waba-id');

        while (empty($wabaId)) {
            $wabaId = $this->ask('Enter your WhatsApp Business Account ID:');
        }

        $verifyToken = $this->option('verify-token');

        while (empty($verifyToken)) {
            $verifyToken = $this->secret('Enter your Verify Token (hidden):');
        }

        $configureService = new WhatsAppConfigureService(
            accessToken: $accessToken,
            appId: $appId,
            appSecret: $appSecret,
            wabaId: $wabaId,
            verifyToken: $verifyToken,
        );

        $configure = $configureService->configure();

        if ($configure['success'] !== true) {
            $this->error($configure['message']);

            return self::FAILURE;
        }

        $this->info($configure['message']);

        return self::SUCCESS;
    }
}
