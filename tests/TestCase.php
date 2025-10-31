<?php

namespace Tests;

use LaravelWhatsApp\WhatsAppServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Workbench\App\Providers\WorkbenchServiceProvider;

abstract class TestCase extends TestbenchTestCase
{
	/**
	 * Register package service providers.
	 * @param \Illuminate\Foundation\Application $app
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
		// In-memory sqlite for fast tests
		$app['config']->set('database.default', 'testing');
		$app['config']->set('database.connections.testing', [
			'driver' => 'sqlite',
			'database' => ':memory:',
			'prefix' => '',
			'foreign_key_constraints' => true,
		]);

		// Minimal required WhatsApp config values
		$app['config']->set('whatsapp.verify_token', 'test-verify-token');
		$app['config']->set('whatsapp.app_secret', 'test-app-secret');
		$app['config']->set('whatsapp.access_token', 'fake-access-token');
		$app['config']->set('whatsapp.queue.connection', 'sync');
	}

	protected function setUp(): void
	{
		parent::setUp();
	}
}