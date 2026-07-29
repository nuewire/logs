<?php

declare(strict_types=1);

namespace Nuewire\Logs\Tests;

use Nuewire\Logs\Support\SystemLogReader;

final class SystemLogReaderTest extends TestCase
{
    public function test_reader_parses_multiline_entries_and_filters_levels(): void
    {
        $directory = storage_path('logs');
        @mkdir($directory, 0777, true);
        file_put_contents($directory.'/app.log', implode("\n", [
            '[2026-07-29 09:00:00] testing.INFO: Started',
            '[2026-07-29 09:01:00] testing.ERROR: Failed',
            '#0 stack line',
            '',
        ]));

        $reader = $this->app->make(SystemLogReader::class);
        $file = $reader->files()[0];
        $result = $reader->read((string) $file['id'], 100, '', 'ERROR');

        self::assertCount(1, $result['entries']);
        self::assertSame('ERROR', $result['entries'][0]['level']);
        self::assertStringContainsString('#0 stack line', $result['entries'][0]['message']);
    }

    public function test_non_log_extensions_are_ignored(): void
    {
        $directory = storage_path('logs');
        @mkdir($directory, 0777, true);
        file_put_contents($directory.'/secret.txt', 'not a log');

        $reader = $this->app->make(SystemLogReader::class);
        self::assertNotContains('secret.txt', array_column($reader->files(), 'name'));
    }
}
