<?php

declare(strict_types=1);

namespace Nuewire\Logs\Tests;

use Nuewire\Logs\Support\SensitiveDataRedactor;

final class SensitiveDataRedactorTest extends TestCase
{
    public function test_nested_sensitive_values_are_redacted_and_long_values_are_truncated(): void
    {
        $redactor = new SensitiveDataRedactor(['password', 'access_token'], '[hidden]', 5);
        $result = $redactor->redact([
            'name' => 'Jonathan',
            'password' => 'secret',
            'nested' => ['access_token' => 'token-value'],
            'password_hash' => 'hash-value',
        ]);

        self::assertSame('Jonat…', $result['name']);
        self::assertSame('[hidden]', $result['password']);
        self::assertSame('[hidden]', $result['nested']['access_token']);
        self::assertSame('[hidden]', $result['password_hash']);
    }
}
