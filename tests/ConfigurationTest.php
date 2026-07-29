<?php

declare(strict_types=1);

namespace Nuewire\Logs\Tests;

use Illuminate\Support\ServiceProvider;
use Nuewire\Logs\LogsServiceProvider;

final class ConfigurationTest extends TestCase
{
    public function test_configuration_uses_nested_nuewire_key(): void
    {
        self::assertSame('id', config('nuewire.logs.locale'));
        self::assertSame('nuewire_request_logs', config('nuewire.logs.request.table'));
        self::assertFalse(config('nuewire.logs.request.capture_payload'));
        self::assertContains('password', config('nuewire.logs.audit.sensitive_keys'));
    }

    public function test_resources_publish_to_shared_nuewire_directories(): void
    {
        self::assertContains(
            config_path('nuewire/logs.php'),
            array_values(ServiceProvider::pathsToPublish(LogsServiceProvider::class, 'nuewire-logs-config')),
        );
        self::assertContains(
            resource_path('views/vendor/nuewire/logs'),
            array_values(ServiceProvider::pathsToPublish(LogsServiceProvider::class, 'nuewire-logs-views')),
        );
        self::assertContains(
            lang_path('vendor/nuewire/logs'),
            array_values(ServiceProvider::pathsToPublish(LogsServiceProvider::class, 'nuewire-logs-translations')),
        );
    }
}
