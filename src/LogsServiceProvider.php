<?php

declare(strict_types=1);

namespace Nuewire\Logs;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;
use Nuewire\Logs\Commands\InstallCommand;
use Nuewire\Logs\Commands\PruneCommand;
use Nuewire\Logs\Http\Middleware\RecordRequestLog;
use Nuewire\Logs\Livewire\AuditTrails;
use Nuewire\Logs\Livewire\RequestLogs;
use Nuewire\Logs\Livewire\SystemLogs;
use Nuewire\Logs\Support\AuditLogger;
use Nuewire\Logs\Support\RequestLogRecorder;
use Nuewire\Logs\Support\SystemLogReader;
use Nuewire\Support\LivewireComponentRegistrar;
use Nuewire\Support\NuewirePaths;

final class LogsServiceProvider extends ServiceProvider
{
    private const CONFIG_KEY = 'nuewire.logs';

    public function register(): void
    {
        $this->replaceConfigRecursivelyFrom(__DIR__.'/../config/nuewire/logs.php', self::CONFIG_KEY);

        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(RequestLogRecorder::class);
        $this->app->singleton(SystemLogReader::class);

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, PruneCommand::class]);
        }

        $this->registerPlatformNavigation();
        $this->registerAclPermissions();
    }

    public function boot(): void
    {
        $paths = $this->app->make(NuewirePaths::class);
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nuewire-logs');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'nuewire-logs');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerLivewireComponents();
        $this->registerRequestMiddleware();

        $this->publishes([
            __DIR__.'/../config/nuewire/logs.php' => $paths->configFile('logs'),
        ], 'nuewire-logs-config');

        $this->publishes([
            __DIR__.'/../resources/views' => $paths->publishedViews('logs'),
        ], 'nuewire-logs-views');

        $this->publishes([
            __DIR__.'/../resources/lang' => $paths->publishedTranslations('logs'),
        ], 'nuewire-logs-translations');
    }

    private function registerLivewireComponents(): void
    {
        $registrar = $this->app->make(LivewireComponentRegistrar::class);
        $registrar->register('nuewire::audit-trails', AuditTrails::class);
        $registrar->register('nuewire::request-logs', RequestLogs::class);
        $registrar->register('nuewire::system-logs', SystemLogs::class);
    }

    private function registerRequestMiddleware(): void
    {
        if (! (bool) config(self::CONFIG_KEY.'.request.auto_register_middleware', true)
            || ! $this->app->bound(HttpKernel::class)) {
            return;
        }

        $kernel = $this->app->make(HttpKernel::class);

        if (method_exists($kernel, 'pushMiddleware')) {
            $kernel->pushMiddleware(RecordRequestLog::class);
        }
    }

    private function registerPlatformNavigation(): void
    {
        $registryClass = 'Nuewire\Platform\Navigation\NavigationRegistry';

        $this->app->afterResolving($registryClass, static function (object $registry): void {
            if (! method_exists($registry, 'register')) {
                return;
            }

            if (! method_exists($registry, 'registerArea')) {
                $group = ['id' => 'Platform Logs', 'en' => 'Platform Logs'];

                $registry->register('audit-trails', [
                    'label' => ['id' => 'Audit Trails', 'en' => 'Audit Trails'],
                    'description' => ['id' => 'Jejak perubahan dan tindakan pengguna.', 'en' => 'User actions and model change history.'],
                    'group' => $group,
                    'component' => 'nuewire::audit-trails',
                    'permission' => 'logs.audit.view',
                    'icon' => 'A',
                    'order' => 10,
                ]);
                $registry->register('request-logs', [
                    'label' => ['id' => 'Request Logs', 'en' => 'Request Logs'],
                    'description' => ['id' => 'Permintaan HTTP, status, dan durasi.', 'en' => 'HTTP requests, statuses, and duration.'],
                    'group' => $group,
                    'component' => 'nuewire::request-logs',
                    'permission' => 'logs.requests.view',
                    'icon' => 'R',
                    'order' => 20,
                ]);
                $registry->register('system-logs', [
                    'label' => ['id' => 'System Logs', 'en' => 'System Logs'],
                    'description' => ['id' => 'Baca file log Laravel secara aman.', 'en' => 'Safely inspect Laravel log files.'],
                    'group' => $group,
                    'component' => 'nuewire::system-logs',
                    'permission' => 'logs.system.view',
                    'icon' => 'S',
                    'order' => 30,
                ]);

                return;
            }

            $registry->register('logs.system', [
                'area' => 'settings',
                'group' => 'platform',
                'slug' => 'system-logs',
                'label' => ['id' => 'System Logs', 'en' => 'System Logs'],
                'description' => ['id' => 'Baca file log Laravel secara aman.', 'en' => 'Safely inspect Laravel log files.'],
                'component' => 'nuewire::system-logs',
                'permission' => 'logs.system.view',
                'icon' => 'system-log',
                'order' => 10,
            ]);

            $registry->register('logs.requests', [
                'area' => 'settings',
                'group' => 'platform',
                'slug' => 'request-logs',
                'label' => ['id' => 'Request Logs', 'en' => 'Request Logs'],
                'description' => ['id' => 'Permintaan HTTP, status, dan durasi.', 'en' => 'HTTP requests, statuses, and duration.'],
                'component' => 'nuewire::request-logs',
                'permission' => 'logs.requests.view',
                'icon' => 'request-log',
                'order' => 20,
            ]);

            $registry->register('logs.audit', [
                'area' => 'settings',
                'group' => 'platform',
                'slug' => 'audit-trails',
                'label' => ['id' => 'Audit Trails', 'en' => 'Audit Trails'],
                'description' => ['id' => 'Jejak perubahan dan tindakan pengguna.', 'en' => 'User actions and model change history.'],
                'component' => 'nuewire::audit-trails',
                'permission' => 'logs.audit.view',
                'icon' => 'audit',
                'order' => 30,
            ]);
        });
    }

    private function registerAclPermissions(): void
    {
        $registryClass = 'Nuewire\\Acl\\Registry\\PermissionRegistry';

        $this->app->afterResolving($registryClass, static function (object $registry): void {
            if (! method_exists($registry, 'registerMany')) {
                return;
            }

            $registry->registerMany([
                'logs.audit.view' => ['id' => 'Melihat audit trails', 'en' => 'View audit trails'],
                'logs.audit.delete' => ['id' => 'Menghapus audit trails', 'en' => 'Delete audit trails'],
                'logs.requests.view' => ['id' => 'Melihat request logs', 'en' => 'View request logs'],
                'logs.requests.delete' => ['id' => 'Menghapus request logs', 'en' => 'Delete request logs'],
                'logs.system.view' => ['id' => 'Melihat system logs', 'en' => 'View system logs'],
                'logs.system.delete' => ['id' => 'Mengosongkan system logs', 'en' => 'Clear system logs'],
            ], 'logs');
        });
    }
}
