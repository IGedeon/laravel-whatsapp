<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

it('runs whatsapp:install and publishes config and migrations', function () {
    $fs = new Filesystem;
    $configPath = config_path('whatsapp.php');
    $migrationDir = database_path('migrations');

    if ($fs->exists($configPath)) {
        $fs->delete($configPath);
    }
    // Remove whatsapp migrations (by filename pattern)
    collect($fs->files($migrationDir))->filter(fn ($f) => str_contains($f->getFilename(), 'whatsapp'))
        ->each(fn ($f) => $fs->delete($f->getRealPath()));

    Artisan::call('whatsapp:install');

    expect($fs->exists($configPath))->toBeTrue();
    $migrationFiles = collect($fs->files($migrationDir))->filter(fn ($f) => str_contains($f->getFilename(), 'whatsapp'));
    expect($migrationFiles->count())->toBeGreaterThan(0);
});

it('respects --no-config flag', function () {
    $fs = new Filesystem;
    $configPath = config_path('whatsapp.php');
    if ($fs->exists($configPath)) {
        $fs->delete($configPath);
    }
    Artisan::call('whatsapp:install --no-config');
    expect($fs->exists($configPath))->toBeFalse();
});

it('respects --no-migrations flag', function () {
    $fs = new Filesystem;
    $migrationDir = database_path('migrations');
    collect($fs->files($migrationDir))->filter(fn ($f) => str_contains($f->getFilename(), 'whatsapp'))
        ->each(fn ($f) => $fs->delete($f->getRealPath()));
    Artisan::call('whatsapp:install --no-migrations');
    $migrationFiles = collect($fs->files($migrationDir))->filter(fn ($f) => str_contains($f->getFilename(), 'whatsapp'));
    expect($migrationFiles->count())->toBe(0);
});

it('runs migrations with --migrate option', function () {
    Artisan::call('whatsapp:install --migrate');
    $schema = app('db')->getSchemaBuilder();
    expect($schema->hasTable('whatsapp_api_phone_numbers'))->toBeTrue();
});

it('force republish does not remove config', function () {
    Artisan::call('whatsapp:install');
    $configPath = config_path('whatsapp.php');
    expect(file_exists($configPath))->toBeTrue();
    Artisan::call('whatsapp:install --force');
    expect(file_exists($configPath))->toBeTrue();
});
