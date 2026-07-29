<?php

declare(strict_types=1);

namespace Nuewire\Logs\Support;

use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    /**
     * @param array<string, mixed> $properties
     */
    public function record(
        string $description,
        ?Model $subject = null,
        array $properties = [],
        Model|int|string|null $causer = null,
        ?string $event = null,
        ?string $logName = null,
    ): mixed {
        $logger = activity($logName ?? (string) config('nuewire.logs.audit.default_log_name', 'platform'));

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        if ($causer !== null) {
            $logger->causedBy($causer);
        }

        if ($properties !== []) {
            $redacted = $this->redactor()->redact($properties);
            $logger->withProperties(is_array($redacted) ? $redacted : []);
        }

        if ($event !== null && $event !== '') {
            $logger->event($event);
        }

        return $logger->log($description);
    }

    private function redactor(): SensitiveDataRedactor
    {
        return new SensitiveDataRedactor(
            array_values(array_filter((array) config('nuewire.logs.audit.sensitive_keys', []), 'is_string')),
            (string) config('nuewire.logs.audit.redacted_value', '[REDACTED]'),
            (int) config('nuewire.logs.audit.max_value_length', 10000),
        );
    }

    /** @param array<string, mixed> $properties */
    public function created(Model $subject, array $properties = [], Model|int|string|null $causer = null): mixed
    {
        return $this->record('created', $subject, $properties, $causer, 'created');
    }

    /** @param array<string, mixed> $properties */
    public function updated(Model $subject, array $properties = [], Model|int|string|null $causer = null): mixed
    {
        return $this->record('updated', $subject, $properties, $causer, 'updated');
    }

    /** @param array<string, mixed> $properties */
    public function deleted(Model $subject, array $properties = [], Model|int|string|null $causer = null): mixed
    {
        return $this->record('deleted', $subject, $properties, $causer, 'deleted');
    }
}
