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
        $this->registerPlatformDashboard();
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
        $registrar->register('nuewire-audit-trails', AuditTrails::class);
        $registrar->register('nuewire-request-logs', RequestLogs::class);
        $registrar->register('nuewire-system-logs', SystemLogs::class);
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
                    'component' => 'nuewire-audit-trails',
                    'permission' => 'logs.audit.view',
                    'icon' => 'A',
                    'order' => 10,
                ]);
                $registry->register('request-logs', [
                    'label' => ['id' => 'Request Logs', 'en' => 'Request Logs'],
                    'description' => ['id' => 'Permintaan HTTP, status, dan durasi.', 'en' => 'HTTP requests, statuses, and duration.'],
                    'group' => $group,
                    'component' => 'nuewire-request-logs',
                    'permission' => 'logs.requests.view',
                    'icon' => 'R',
                    'order' => 20,
                ]);
                $registry->register('system-logs', [
                    'label' => ['id' => 'System Logs', 'en' => 'System Logs'],
                    'description' => ['id' => 'Baca file log Laravel secara aman.', 'en' => 'Safely inspect Laravel log files.'],
                    'group' => $group,
                    'component' => 'nuewire-system-logs',
                    'permission' => 'logs.system.view',
                    'icon' => 'S',
                    'order' => 30,
                ]);

                return;
            }

            $registry->register('logs.system', [
                'area' => 'plugin',
                'group' => 'platform',
                'slug' => 'system-logs',
                'label' => ['id' => 'System Logs', 'en' => 'System Logs'],
                'description' => ['id' => 'Baca file log Laravel secara aman.', 'en' => 'Safely inspect Laravel log files.'],
                'component' => 'nuewire-system-logs',
                'permission' => 'logs.system.view',
                'icon' => 'system-log',
                'order' => 10,
            ]);

            $registry->register('logs.requests', [
                'area' => 'plugin',
                'group' => 'platform',
                'slug' => 'request-logs',
                'label' => ['id' => 'Request Logs', 'en' => 'Request Logs'],
                'description' => ['id' => 'Permintaan HTTP, status, dan durasi.', 'en' => 'HTTP requests, statuses, and duration.'],
                'component' => 'nuewire-request-logs',
                'permission' => 'logs.requests.view',
                'icon' => 'request-log',
                'order' => 20,
            ]);

            $registry->register('logs.audit', [
                'area' => 'plugin',
                'group' => 'platform',
                'slug' => 'audit-trails',
                'label' => ['id' => 'Audit Trails', 'en' => 'Audit Trails'],
                'description' => ['id' => 'Jejak perubahan dan tindakan pengguna.', 'en' => 'User actions and model change history.'],
                'component' => 'nuewire-audit-trails',
                'permission' => 'logs.audit.view',
                'icon' => 'audit',
                'order' => 30,
            ]);
        });
    }


    private function registerPlatformDashboard(): void
    {
        $registryClass = 'Nuewire\\Platform\\Dashboard\\DashboardRegistry';

        $this->app->afterResolving($registryClass, static function (object $registry): void {
            if (! method_exists($registry, 'register')) {
                return;
            }

            if (method_exists($registry, 'registerGroup')) {
                $registry->registerGroup('monitoring', [
                    'label' => ['id' => 'Monitoring', 'en' => 'Monitoring'],
                    'order' => 40,
                ]);
            }

            $requestLogAvailable = static function (): bool {
                try {
                    $connection = trim((string) config('nuewire.logs.request.connection', ''));
                    return \Illuminate\Support\Facades\Schema::connection($connection !== '' ? $connection : null)
                        ->hasTable((string) config('nuewire.logs.request.table', 'nuewire_request_logs'));
                } catch (\Throwable) {
                    return false;
                }
            };

            $registry->register('logs.request-health', [
                'group' => 'monitoring',
                'label' => ['id' => 'Aktivitas Request', 'en' => 'Request Activity'],
                'description' => ['id' => 'Volume request HTTP dalam periode pilihan.', 'en' => 'HTTP request volume during the selected period.'],
                'type' => 'chart',
                'permission' => 'logs.requests.view',
                'visible' => $requestLogAvailable,
                'width' => 8,
                'min_width' => 6,
                'default' => true,
                'cache_ttl' => 300,
                'cache_scope' => 'global',
                'refresh_interval' => 300,
                'settings' => [
                    'period' => [
                        'type' => 'select',
                        'label' => ['id' => 'Periode', 'en' => 'Period'],
                        'options' => [
                            '24h' => ['id' => '24 jam', 'en' => '24 hours'],
                            '7d' => ['id' => '7 hari', 'en' => '7 days'],
                            '30d' => ['id' => '30 hari', 'en' => '30 days'],
                        ],
                        'default' => '24h',
                    ],
                ],
                'default_settings' => ['period' => '24h'],
                'resolver' => static function (object $context): array {
                    $period = (string) ($context->settings['period'] ?? '24h');
                    $points = $period === '24h' ? 12 : ($period === '7d' ? 7 : 10);
                    $stepHours = $period === '24h' ? 2 : ($period === '7d' ? 24 : 72);
                    $labels = [];
                    $values = [];
                    $total = 0;
                    $start = now()->subHours($points * $stepHours);

                    for ($index = 0; $index < $points; $index++) {
                        $from = $start->copy()->addHours($index * $stepHours);
                        $to = $from->copy()->addHours($stepHours);
                        $count = \Nuewire\Logs\Models\RequestLog::query()->whereBetween('created_at', [$from, $to])->count();
                        $labels[] = $stepHours < 24 ? $from->format('H:i') : $from->format('d M');
                        $values[] = $count;
                        $total += $count;
                    }

                    return [
                        'labels' => $labels,
                        'values' => $values,
                        'meta' => number_format($total).' '.($context->locale === 'en' ? 'requests in period' : 'request pada periode'),
                    ];
                },
                'order' => 10,
            ]);

            $registry->register('logs.error-rate', [
                'group' => 'monitoring',
                'label' => ['id' => 'Error Request', 'en' => 'Request Errors'],
                'description' => ['id' => 'Persentase response HTTP 5xx selama 24 jam.', 'en' => 'Percentage of HTTP 5xx responses during 24 hours.'],
                'type' => 'stat',
                'permission' => 'logs.requests.view',
                'visible' => $requestLogAvailable,
                'width' => 3,
                'default' => true,
                'cache_ttl' => 300,
                'cache_scope' => 'global',
                'refresh_interval' => 300,
                'resolver' => static function (object $context): array {
                    $query = \Nuewire\Logs\Models\RequestLog::query()->where('created_at', '>=', now()->subDay());
                    $total = (clone $query)->count();
                    $errors = (clone $query)->where('status_code', '>=', 500)->count();
                    $rate = $total > 0 ? ($errors / $total) * 100 : 0;

                    return [
                        'value' => number_format($rate, 1).'%',
                        'meta' => number_format($errors).' / '.number_format($total).' '.($context->locale === 'en' ? 'requests' : 'request'),
                        'url' => $context->route('plugin', 'request-logs'),
                    ];
                },
                'order' => 20,
            ]);

            $registry->register('logs.recent-system-errors', [
                'group' => 'monitoring',
                'label' => ['id' => 'Error Sistem Terbaru', 'en' => 'Recent System Errors'],
                'description' => ['id' => 'Request gagal terbaru dengan status 5xx atau exception.', 'en' => 'Latest failed requests with 5xx status or exceptions.'],
                'type' => 'feed',
                'permission' => 'logs.requests.view',
                'visible' => $requestLogAvailable,
                'width' => 8,
                'default' => true,
                'cache_ttl' => 120,
                'cache_scope' => 'global',
                'resolver' => static function (object $context): array {
                    $url = $context->route('plugin', 'request-logs');
                    $items = \Nuewire\Logs\Models\RequestLog::query()
                        ->where(static fn ($query) => $query->where('status_code', '>=', 500)->orWhereNotNull('exception_class'))
                        ->latest('created_at')->limit(6)->get()->map(static fn ($log): array => [
                            'title' => $log->method.' '.$log->path,
                            'meta' => $log->status_code.' · '.optional($log->created_at)->format('Y-m-d H:i:s').' · '.($log->exception_class ?: $log->duration_ms.' ms'),
                            'url' => $url,
                        ])->all();

                    return ['items' => $items, 'empty' => $context->locale === 'en' ? 'No recent server errors.' : 'Tidak ada error server terbaru.'];
                },
                'order' => 30,
            ]);

            $registry->register('logs.recent-audits', [
                'group' => 'monitoring',
                'label' => ['id' => 'Audit Terbaru', 'en' => 'Recent Audits'],
                'description' => ['id' => 'Aktivitas terbaru dari Spatie Activitylog.', 'en' => 'Latest activities from Spatie Activitylog.'],
                'type' => 'feed',
                'permission' => 'logs.audit.view',
                'visible' => static fn (): bool => class_exists('Spatie\\Activitylog\\Models\\Activity'),
                'width' => 4,
                'default' => true,
                'cache_ttl' => 120,
                'cache_scope' => 'global',
                'resolver' => static function (object $context): array {
                    $class = 'Spatie\\Activitylog\\Models\\Activity';
                    $url = $context->route('plugin', 'audit-trails');
                    $items = $class::query()->latest('id')->limit(6)->get()->map(static fn ($activity): array => [
                        'title' => (string) ($activity->description ?: $activity->event ?: 'activity'),
                        'meta' => (string) ($activity->log_name ?: 'default').' · '.optional($activity->created_at)->format('Y-m-d H:i:s'),
                        'url' => $url,
                    ])->all();

                    return ['items' => $items, 'empty' => $context->locale === 'en' ? 'No audit trail yet.' : 'Belum ada audit trail.'];
                },
                'order' => 40,
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
