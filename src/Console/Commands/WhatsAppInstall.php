<?php

namespace LaravelWhatsApp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class WhatsAppInstall extends Command
{
    protected $signature = 'whatsapp:install \
        {--force : Overwrite existing published files} \
        {--no-config : Skip publishing config file} \
        {--no-migrations : Skip publishing migrations} \
        {--migrate : Run migrations after publishing}';

    protected $description = 'Publish Laravel WhatsApp package assets (config, migrations) and optionally run migrations.';

    /**
     * Internal buffer for test assertions (since Artisan::output only captures last nested call).
     */
    private string $buffer = '';

    public function handle(): int
    {
        $this->info('Starting Laravel WhatsApp installation...');
        $this->appendBuffer('Starting Laravel WhatsApp installation...');

        $publishedAnything = false;

        // Publish config
        if (! $this->option('no-config')) {
            $this->publishTag('whatsapp-config');
            $publishedAnything = true;
        } else {
            $this->line(' - Skipping config publish');
            $this->appendBuffer('Skipping config publish');
        }

        // Publish migrations
        if (! $this->option('no-migrations')) {
            $this->publishTag('whatsapp-migrations');
            $publishedAnything = true;
        } else {
            $this->line(' - Skipping migrations publish');
            $this->appendBuffer('Skipping migrations publish');
        }

        if (! $publishedAnything) {
            $this->warn('Nothing was published. Use without --no-* options to publish resources.');
            $this->appendBuffer('Nothing was published');
        }

        // Optionally run migrations
        if ($this->option('migrate')) {
            $this->line('Running migrations...');
            $this->appendBuffer('Running migrations...');
            Artisan::call('migrate', [
                '--force' => $this->option('force'),
            ]);
            $this->output->write(Artisan::output());
        } else {
            $this->line('Skipping migrate step (use --migrate to run).');
            $this->appendBuffer('Skipping migrate step');
        }

        $this->newLine();
        $this->info('Installation complete. Next steps:');
        $this->appendBuffer('Installation complete');
        $this->line(' 1. Set environment variables (WHATSAPP_ACCESS_TOKEN, WHATSAPP_VERIFY_TOKEN, etc).');
        $this->line(' 2. Configure listener in config/whatsapp.php if you need custom behavior.');
        $this->line(' 3. Point your webhook route to the package route group (see README).');

        return self::SUCCESS;
    }

    private function publishTag(string $tag): void
    {
        $params = [
            '--provider' => 'LaravelWhatsApp\\WhatsAppServiceProvider',
            '--tag' => $tag,
        ];
        if ($this->option('force')) {
            $params['--force'] = true;
        }

        $this->line("Publishing tag [$tag]...");
        $this->appendBuffer("Publishing tag [$tag]...");
        Artisan::call('vendor:publish', $params);
        $output = trim(Artisan::output());
        if ($output === 'No publishable resources for tag ['.$tag.'].') {
            $this->warn("Nothing to publish for tag [$tag] (already published or missing).");
            $this->appendBuffer("Nothing to publish for tag [$tag]");
        } else {
            $this->output->write($output."\n");
            $this->appendBuffer($output);
        }
    }

    private function appendBuffer(string $line): void
    {
        $this->buffer .= $line."\n";
    }

    // Accessor for tests via reflection or by re-calling command? We can expose publicly.
    public function getBufferedOutput(): string
    {
        return $this->buffer;
    }
}
