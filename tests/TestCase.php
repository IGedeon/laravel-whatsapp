<?php

namespace Tests;

use LaravelWhatsApp\WhatsAppServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

abstract class TestCase extends TestbenchTestCase
{
    /**
     * Register package service providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app)
    {
        return [
            WhatsAppServiceProvider::class,
        ];
    }

    /**
     * Setup the test environment.
     */
    protected function getEnvironmentSetUp($app)
    {
        // File-based sqlite for stability across artisan migrate within process
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Minimal required WhatsApp config values
        $app['config']->set('whatsapp.queue.connection', 'sync');
    }

    /**
     * Ensure package migrations are loaded BEFORE RefreshDatabase runs.
     * Testbench will call this automatically prior a migration refresh.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        // Run migrations explicitly so the first test already has schema.
        $this->artisan('migrate');
    }
}
