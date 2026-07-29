<?php

declare(strict_types=1);

namespace Nuewire\Logs\Support;

use Illuminate\Http\UploadedFile;
use Stringable;

final class SensitiveDataRedactor
{
    /** @param array<int, string> $sensitiveKeys */
    public function __construct(
        private readonly array $sensitiveKeys,
        private readonly string $redactedValue,
        private readonly int $maxValueLength,
    ) {
    }

    public function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitive($key)) {
            return $this->redactedValue;
        }

        if (is_array($value)) {
            $result = [];

            foreach ($value as $childKey => $childValue) {
                $result[$childKey] = $this->redact($childValue, (string) $childKey);
            }

            return $result;
        }

        if ($value instanceof UploadedFile) {
            return [
                'name' => $value->getClientOriginalName(),
                'mime' => $value->getClientMimeType(),
                'size' => $value->getSize(),
            ];
        }

        if (is_object($value)) {
            if ($value instanceof Stringable) {
                return $this->truncate((string) $value);
            }

            return '[OBJECT '.get_debug_type($value).']';
        }

        if (is_string($value)) {
            return $this->truncate($value);
        }

        return $value;
    }

    private function isSensitive(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '.'], '_', $key));

        foreach ($this->sensitiveKeys as $sensitiveKey) {
            $sensitive = strtolower(str_replace(['-', '.'], '_', $sensitiveKey));

            if ($normalized === $sensitive
                || str_ends_with($normalized, '_'.$sensitive)
                || str_starts_with($normalized, $sensitive.'_')
                || str_contains($normalized, '_'.$sensitive.'_')) {
                return true;
            }
        }

        return false;
    }

    private function truncate(string $value): string
    {
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);

        if ($this->maxValueLength < 1 || $length <= $this->maxValueLength) {
            return $value;
        }

        $truncated = function_exists('mb_substr')
            ? mb_substr($value, 0, $this->maxValueLength)
            : substr($value, 0, $this->maxValueLength);

        return $truncated.'…';
    }
}
