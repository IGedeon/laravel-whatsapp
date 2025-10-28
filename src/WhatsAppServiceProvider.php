<?php

namespace LaravelWhatsApp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use LaravelWhatsApp\Events\WhatsAppMessageReceived;
use LaravelWhatsApp\Services\WhatsAppMessageService;

class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/whatsapp.php', 'whatsapp');
        
        $this->app->singleton(WhatsAppMessageService::class, function($app){
            return new WhatsAppMessageService();
        });
    }

    public function boot(): void
    {
        // Register publishable resources (unconditional so vendor:publish always sees them)
        $this->publishes([
            __DIR__.'/Config/whatsapp.php' => config_path('whatsapp.php'),
        ], 'whatsapp-config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'whatsapp-migrations');

        $this->publishes([
            __DIR__.'/Config/whatsapp.php' => config_path('whatsapp.php'),
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'whatsapp');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \LaravelWhatsApp\Console\Commands\WhatsAppInstall::class,
            ]);
        }

        // Routes
        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');

        // Registro dinámico de listener si está configurado
        $listener = config('whatsapp.listeners.whatsapp_message_received');
        if ($listener) {
            Event::listen(WhatsAppMessageReceived::class, $listener);
        }
    }
}
