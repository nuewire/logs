<?php

declare(strict_types=1);

namespace Nuewire\Logs\Tests;

use Illuminate\Support\Facades\Artisan;

final class CommandRegistrationTest extends TestCase
{
    public function test_commands_are_registered(): void
    {
        self::assertTrue(Artisan::has('nuewire:logs:install'));
        self::assertTrue(Artisan::has('nuewire:logs:prune'));
    }
}
