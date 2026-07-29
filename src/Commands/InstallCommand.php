<?php

declare(strict_types=1);

namespace Nuewire\Logs\Commands;

use Illuminate\Console\Command;
use Spatie\Activitylog\ActivitylogServiceProvider;

final class InstallCommand extends Command
{
    protected $signature = 'nuewire:logs:install
        {--migrate : Run database migrations}
        {--force : Overwrite published Spatie Activitylog files}';

    protected $description = 'Install Nuewire logs and Spatie activity logging';

    public function handle(): int
    {
        $arguments = ['--provider' => ActivitylogServiceProvider::class];

        if ($this->option('force')) {
            $arguments['--force'] = true;
        }

        if ($this->call('vendor:publish', $arguments) !== self::SUCCESS) {
            return self::FAILURE;
        }

        if ($this->option('migrate') && $this->call('migrate', ['--force' => (bool) $this->option('force')]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        if (! $this->option('migrate')) {
            $this->components->info('Jalankan php artisan migrate untuk membuat activity_log dan nuewire_request_logs.');
        }

        $this->components->info('Nuewire Logs siap. Gunakan Nuewire\\Logs\\Support\\AuditLogger untuk audit manual.');

        return self::SUCCESS;
    }
}
