<?php

declare(strict_types=1);

namespace Nuewire\Logs\Tests;

use Livewire\Livewire;

final class ComponentRegistrationTest extends TestCase
{
    public function test_audit_component_is_registered_and_handles_missing_table(): void
    {
        Livewire::test('nuewire::audit-trails')
            ->assertStatus(200)
            ->assertSet('locale', 'id')
            ->assertSee('Audit Trails')
            ->assertSee('nuewire:logs:install --migrate');
    }

    public function test_request_component_is_registered_and_handles_missing_table(): void
    {
        Livewire::test('nuewire::request-logs', ['locale' => 'en'])
            ->assertStatus(200)
            ->assertSet('locale', 'en')
            ->assertSee('Request Logs')
            ->assertSee('nuewire:logs:install --migrate');
    }

    public function test_system_component_reads_configured_laravel_log(): void
    {
        $directory = storage_path('logs');
        @mkdir($directory, 0777, true);
        file_put_contents($directory.'/laravel.log', "[2026-07-29 10:00:00] testing.ERROR: Example failure\n#0 trace\n");

        Livewire::test('nuewire::system-logs', ['locale' => 'en'])
            ->assertStatus(200)
            ->assertSee('System Logs')
            ->assertSee('Example failure')
            ->assertSee('ERROR');
    }
}
