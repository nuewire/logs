<?php

declare(strict_types=1);

namespace Nuewire\Logs\Tests;

use Livewire\LivewireServiceProvider;
use Nuewire\Logs\LogsServiceProvider;
use Nuewire\Support\SupportServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Activitylog\ActivitylogServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SupportServiceProvider::class,
            LivewireServiceProvider::class,
            ActivitylogServiceProvider::class,
            LogsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('app.locale', 'en');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('nuewire.logs.locale', 'id');
        $app['config']->set('nuewire.logs.authorization.require_authenticated_user', false);
        $app['config']->set('nuewire.logs.request.auto_register_middleware', false);
        $app['config']->set('nuewire.logs.system.paths', [$app->storagePath('logs')]);
    }
}
