<?php

declare(strict_types=1);

namespace Nuewire\Logs\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

final class SystemLogReader
{
    /** @return array<int, array<string, mixed>> */
    public function files(): array
    {
        return array_map(static function (array $file): array {
            unset($file['path']);

            return $file;
        }, $this->discover());
    }

    /**
     * @return array{file: array<string, mixed>|null, entries: array<int, array<string, mixed>>, truncated: bool}
     */
    public function read(string $id, int $lineLimit, string $search = '', string $level = ''): array
    {
        $file = $this->find($id);

        if ($file === null) {
            return ['file' => null, 'entries' => [], 'truncated' => false];
        }

        $maxLines = max(1, (int) config('nuewire.logs.system.max_lines', 2000));
        $lineLimit = max(1, min($lineLimit, $maxLines));
        $lines = $this->tail((string) $file['path'], $lineLimit);
        $entries = $this->parse($lines);
        $search = $this->lower(trim($search));
        $level = strtoupper(trim($level));

        $entries = array_values(array_filter($entries, static function (array $entry) use ($search, $level): bool {
            if ($level !== '' && strtoupper((string) $entry['level']) !== $level) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = (string) $entry['datetime'].' '.
                (string) $entry['environment'].' '.
                (string) $entry['level'].' '.
                (string) $entry['message'];

            $normalized = function_exists('mb_strtolower') ? mb_strtolower($haystack) : strtolower($haystack);

            return str_contains($normalized, $search);
        }));

        unset($file['path']);

        return [
            'file' => $file,
            'entries' => array_reverse($entries),
            'truncated' => count($lines) >= $lineLimit,
        ];
    }

    public function clear(string $id): bool
    {
        $file = $this->find($id);

        if ($file === null) {
            return false;
        }

        $handle = @fopen((string) $file['path'], 'wb');

        if ($handle === false) {
            return false;
        }

        fclose($handle);

        return true;
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }

    /** @return array<int, array<string, mixed>> */
    private function discover(): array
    {
        $extensions = array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(ltrim((string) $value, '.')),
            (array) config('nuewire.logs.system.extensions', ['log']),
        )));
        $files = [];

        foreach ((array) config('nuewire.logs.system.paths', [storage_path('logs')]) as $configuredPath) {
            if (! is_string($configuredPath) || $configuredPath === '') {
                continue;
            }

            $root = realpath($configuredPath);

            if ($root === false || ! is_dir($root)) {
                continue;
            }

            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
                );

                /** @var SplFileInfo $item */
                foreach ($iterator as $item) {
                    if (! $item->isFile() || ! in_array(strtolower($item->getExtension()), $extensions, true)) {
                        continue;
                    }

                    $path = $item->getRealPath();

                    if ($path === false || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)) {
                        continue;
                    }

                    $relative = ltrim(substr($path, strlen($root)), DIRECTORY_SEPARATOR);
                    $files[] = [
                        'id' => hash('sha256', $path),
                        'name' => $relative !== '' ? $relative : $item->getFilename(),
                        'size' => $item->getSize(),
                        'modified_at' => $item->getMTime(),
                        'path' => $path,
                    ];
                }
            } catch (Throwable) {
                continue;
            }
        }

        usort($files, static function (array $a, array $b): int {
            $modified = (int) $b['modified_at'] <=> (int) $a['modified_at'];

            return $modified !== 0 ? $modified : (string) $a['name'] <=> (string) $b['name'];
        });

        return $files;
    }

    /** @return array<string, mixed>|null */
    private function find(string $id): ?array
    {
        foreach ($this->discover() as $file) {
            if (hash_equals((string) $file['id'], $id)) {
                return $file;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function tail(string $path, int $lineLimit): array
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        try {
            fseek($handle, 0, SEEK_END);
            $position = ftell($handle);

            if ($position === false || $position === 0) {
                return [];
            }

            $buffer = '';
            $chunkSize = 8192;

            while ($position > 0 && substr_count($buffer, "\n") <= $lineLimit) {
                $read = min($chunkSize, $position);
                $position -= $read;
                fseek($handle, $position);
                $chunk = fread($handle, $read);

                if ($chunk === false) {
                    break;
                }

                $buffer = $chunk.$buffer;
            }

            $lines = preg_split('/\r\n|\n|\r/', $buffer) ?: [];

            if ($lines !== [] && end($lines) === '') {
                array_pop($lines);
            }

            return array_slice($lines, -$lineLimit);
        } finally {
            fclose($handle);
        }
    }

    /** @param array<int, string> $lines @return array<int, array<string, mixed>> */
    private function parse(array $lines): array
    {
        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^\[(?<datetime>[^\]]+)]\s+(?<environment>[A-Za-z0-9_-]+)\.(?<level>[A-Z]+):\s?(?<message>.*)$/', $line, $matches) === 1) {
                if ($current !== null) {
                    $entries[] = $current;
                }

                $current = [
                    'datetime' => $matches['datetime'],
                    'environment' => $matches['environment'],
                    'level' => strtoupper($matches['level']),
                    'message' => $matches['message'],
                ];

                continue;
            }

            if ($current === null) {
                if (trim($line) !== '') {
                    $current = [
                        'datetime' => '',
                        'environment' => '',
                        'level' => 'RAW',
                        'message' => $line,
                    ];
                }

                continue;
            }

            $current['message'] .= "\n".$line;
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        return $entries;
    }
}
