<?php

declare(strict_types=1);

namespace Nuewire\Logs\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Nuewire\Logs\Models\RequestLog;
use Throwable;

final class PruneCommand extends Command
{
    protected $signature = 'nuewire:logs:prune
        {--audit-days= : Override audit trail retention days}
        {--request-days= : Override request log retention days}';

    protected $description = 'Delete expired Nuewire audit trails and request logs';

    public function handle(): int
    {
        $auditDays = max(0, (int) ($this->option('audit-days') ?? config('nuewire.logs.audit.retention_days', 365)));
        $requestDays = max(0, (int) ($this->option('request-days') ?? config('nuewire.logs.request.retention_days', 30)));
        $auditDeleted = $this->pruneAudit($auditDays);
        $requestDeleted = $this->pruneRequests($requestDays);

        $this->components->info("Audit trails dihapus: {$auditDeleted}; request logs dihapus: {$requestDeleted}.");

        return self::SUCCESS;
    }

    private function pruneAudit(int $days): int
    {
        if ($days < 1) {
            return 0;
        }

        try {
            $class = (string) config('activitylog.activity_model', 'Spatie\\Activitylog\\Models\\Activity');
            /** @var Model $model */
            $model = app($class);

            if (! Schema::connection($model->getConnectionName())->hasTable($model->getTable())) {
                return 0;
            }

            return $model->newQuery()->where('created_at', '<', now()->subDays($days))->delete();
        } catch (Throwable) {
            return 0;
        }
    }

    private function pruneRequests(int $days): int
    {
        if ($days < 1) {
            return 0;
        }

        try {
            $model = new RequestLog();

            if (! Schema::connection($model->getConnectionName())->hasTable($model->getTable())) {
                return 0;
            }

            return RequestLog::query()->where('created_at', '<', now()->subDays($days))->delete();
        } catch (Throwable) {
            return 0;
        }
    }
}
