<?php

declare(strict_types=1);

namespace Nuewire\Logs\Tests;

final class PermissionRegistrationTest extends TestCase
{
    public function test_log_permissions_are_registered_when_acl_resolves_later(): void
    {
        $abstract = 'Nuewire\\Acl\\Registry\\PermissionRegistry';
        $this->app->singleton($abstract, static fn (): FakeLogsPermissionRegistry => new FakeLogsPermissionRegistry());

        /** @var FakeLogsPermissionRegistry $registry */
        $registry = $this->app->make($abstract);

        self::assertArrayHasKey('logs.audit.view', $registry->permissions);
        self::assertArrayHasKey('logs.requests.delete', $registry->permissions);
        self::assertArrayHasKey('logs.system.view', $registry->permissions);
        self::assertSame('logs', $registry->group);
    }
}

final class FakeLogsPermissionRegistry
{
    /** @var array<string, mixed> */
    public array $permissions = [];
    public string $group = '';

    /** @param array<string, mixed> $permissions */
    public function registerMany(array $permissions, string $group): self
    {
        $this->permissions = $permissions;
        $this->group = $group;

        return $this;
    }
}
