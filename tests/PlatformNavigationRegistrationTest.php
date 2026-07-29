<?php

declare(strict_types=1);

namespace Nuewire\Logs\Tests;

final class PlatformNavigationRegistrationTest extends TestCase
{
    public function test_three_log_pages_are_registered_under_plugin_platform(): void
    {
        $abstract = 'Nuewire\\Platform\\Navigation\\NavigationRegistry';
        $this->app->singleton($abstract, static fn (): FakeLogsNavigationRegistry => new FakeLogsNavigationRegistry());

        /** @var FakeLogsNavigationRegistry $registry */
        $registry = $this->app->make($abstract);

        self::assertSame(
            ['logs.system', 'logs.requests', 'logs.audit'],
            array_keys($registry->pages),
        );
        self::assertSame('plugin', $registry->pages['logs.system']['area']);
        self::assertSame('platform', $registry->pages['logs.system']['group']);
        self::assertSame('nuewire-request-logs', $registry->pages['logs.requests']['component']);
        self::assertSame(30, $registry->pages['logs.audit']['order']);
    }
}

final class FakeLogsNavigationRegistry
{
    /** @var array<string, array<string, mixed>> */
    public array $pages = [];

    /** @param array<string, mixed> $area */
    public function registerArea(string $id, array $area = []): self
    {
        return $this;
    }

    /** @param array<string, mixed> $page */
    public function register(string $id, array $page): self
    {
        $this->pages[$id] = $page;

        return $this;
    }
}
