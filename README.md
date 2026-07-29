# Nuewire Logs

Platform logging package for the Nuewire Laravel suite.

Panduan implementasi berbahasa Indonesia tersedia di [`docs/PANDUAN.md`](docs/PANDUAN.md).

It registers three pages under **Plugin → Platform** when `nuewire/platform` 2 is installed:

- **Audit Trails** — user actions and model changes through `spatie/laravel-activitylog`.
- **Request Logs** — HTTP method, path, route, status, duration, user, IP, and optional sanitized request data.
- **System Logs** — a bounded, read-only-by-default viewer for Laravel `.log` files.

## Compatibility

```text
PHP             Laravel          spatie/laravel-activitylog
8.2–8.3         11–12            v4
8.4+            11               v4
8.4+            12               v4 or v5
8.4+            13               v5
```

The package constraint is `^4.10.2|^5.0`. Composer resolves the compatible Activitylog major for the application's Laravel and PHP versions.

## Install

```bash
composer require nuewire/logs
php artisan nuewire:logs:install --migrate
php artisan optimize:clear
```

The install command publishes Spatie's version-specific config and migration. Nuewire's request-log migration is loaded automatically by the package.

Without `--migrate`, run migrations separately:

```bash
php artisan nuewire:logs:install
php artisan migrate
```

## Components

```blade
<livewire:nuewire-audit-trails />
<livewire:nuewire-request-logs />
<livewire:nuewire-system-logs />
```

With `nuewire/platform` 2, these components appear automatically as:

```text
Plugin
└── Platform
    ├── System Logs
    ├── Request Logs
    └── Audit Trails
```

## Recommended audit API

Use `Nuewire\Logs\Support\AuditLogger` inside controllers, actions, services, jobs, listeners, or Livewire actions. This wrapper uses Spatie's stable `activity()` API and sanitizes sensitive property keys configured in `nuewire.logs.audit.sensitive_keys`.

### Record an update in a controller or service

Capture the old state before persistence, write the change, then log only after the operation succeeds.

```php
use App\Models\User;
use Illuminate\Http\Request;
use Nuewire\Logs\Support\AuditLogger;

final class UpdateUserProfile
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {
    }

    public function handle(Request $request, User $user, array $validated): User
    {
        $before = $user->only(['name', 'email']);

        $user->fill($validated)->save();

        $this->audit->record(
            description: 'user profile updated',
            subject: $user,
            properties: [
                'old' => $before,
                'attributes' => $user->only(['name', 'email']),
                'request_id' => $request->header('X-Request-Id'),
            ],
            causer: $request->user(),
            event: 'updated',
            logName: 'users',
        );

        return $user;
    }
}
```

### Record a domain action

Not every audit record represents a CRUD event. Use a specific event and description for approvals, exports, impersonation, permission changes, or configuration changes.

```php
$audit->record(
    description: 'purchase order approved',
    subject: $purchaseOrder,
    properties: [
        'attributes' => [
            'status' => 'approved',
            'approved_at' => now()->toIso8601String(),
        ],
        'old' => ['status' => $previousStatus],
    ],
    causer: auth()->user(),
    event: 'approved',
    logName: 'purchase-orders',
);
```

### Record a change without an Eloquent subject

This is useful for platform settings, exports, maintenance actions, or external resources.

```php
$audit->record(
    description: 'mail provider changed',
    properties: [
        'old' => ['driver' => $oldDriver],
        'attributes' => ['driver' => $newDriver],
    ],
    causer: auth()->user(),
    event: 'settings.updated',
    logName: 'platform-settings',
);
```

### Insert audit logging into a Livewire action

Laravel's container can inject `AuditLogger` into a public Livewire action. Keep the log after the successful state change.

```php
use Illuminate\Support\Facades\Auth;
use Nuewire\Logs\Support\AuditLogger;

public function save(AuditLogger $audit): void
{
    $this->validate();

    $before = [
        'timezone' => $this->settings->timezone,
    ];

    $this->settings->update([
        'timezone' => $this->timezone,
    ]);

    $audit->record(
        description: 'platform timezone updated',
        subject: $this->settings,
        properties: [
            'old' => $before,
            'attributes' => ['timezone' => $this->timezone],
        ],
        causer: Auth::user(),
        event: 'updated',
        logName: 'platform-settings',
    );
}
```

For a database transaction, log inside the transaction after the write, or after commit when the audit row must never outlive a rolled-back domain change.

```php
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($audit, $order): void {
    $order->update(['status' => 'cancelled']);

    $audit->record(
        description: 'order cancelled',
        subject: $order,
        causer: auth()->user(),
        event: 'cancelled',
        logName: 'orders',
    );
});
```

### Convenience methods

```php
$audit->created($model, ['attributes' => $model->only(['name'])], auth()->user());
$audit->updated($model, ['old' => $old, 'attributes' => $new], auth()->user());
$audit->deleted($model, ['old' => $snapshot], auth()->user());
```

### Use Spatie directly

The underlying package remains available when advanced Activitylog features are needed.

```php
activity('orders')
    ->performedOn($order)
    ->causedBy(auth()->user())
    ->event('approved')
    ->withProperties([
        'old' => ['status' => 'pending'],
        'attributes' => ['status' => 'approved'],
    ])
    ->log('order approved');
```

Direct Spatie calls do not pass through Nuewire's `AuditLogger` sanitizer. Never include passwords, tokens, cookies, authorization headers, API secrets, payment secrets, or unnecessary personal data.

## Automatic Eloquent model logging

Use automatic model logging when ordinary Eloquent create/update/delete events are the audit boundary. Prefer manual domain logs for semantic actions such as `approved`, `published`, `refunded`, or `permission.granted`.

### Activitylog v4

```php
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class Product extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('products')
            ->logOnly(['name', 'price', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
```

### Activitylog v5

```php
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class Product extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('products')
            ->logOnly(['name', 'price', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
```

Do not use `logAll()` blindly on models containing credentials, internal tokens, encrypted payloads, large JSON documents, or high-volume counters. Explicit allowlists are safer and produce more useful audit records.

## Request Logs

`RecordRequestLog` is appended to Laravel's global HTTP middleware stack by default. It records after the response has been produced and never replaces the application response when log persistence fails.

Default fields:

- UUID request ID, also returned as `X-Request-Id`.
- HTTP method, path, route name, status code, and duration.
- IP address, user agent, authenticated user type and ID.
- Query string after sensitive-key redaction.
- Exception class when a request terminates with an exception.

Request payloads and headers are **disabled by default**. Enable them only after reviewing privacy and retention requirements:

```env
NUEWIRE_REQUEST_LOG_ENABLED=true
NUEWIRE_REQUEST_LOG_AUTO_MIDDLEWARE=true
NUEWIRE_REQUEST_LOG_RETENTION_DAYS=30
NUEWIRE_REQUEST_LOG_SLOW_MS=1000
```

Publish the config to change capture policy, exclusions, allowlisted headers, or sensitive keys:

```bash
php artisan vendor:publish --tag=nuewire-logs-config
```

To register the middleware manually:

```php
use Nuewire\Logs\Http\Middleware\RecordRequestLog;

// bootstrap/app.php
->withMiddleware(function ($middleware): void {
    $middleware->append(RecordRequestLog::class);
})
```

Set `request.auto_register_middleware` to `false` when registering it manually, otherwise requests can be logged twice.

## System Logs

System Logs discovers only configured extensions beneath configured root directories. File identifiers are hashes; clients cannot submit an arbitrary filesystem path. Symlinks resolving outside a configured root are rejected.

Default root:

```php
'paths' => [storage_path('logs')],
'extensions' => ['log'],
```

The reader tails a bounded number of lines rather than loading the entire file. Clearing a selected file requires `logs.system.delete`.

Keep log directories outside public web roots, restrict the page with ACL, and avoid writing secrets to Laravel logs in the first place.

## Permissions

When `nuewire/acl` is enabled, the package registers:

```text
logs.audit.view
logs.audit.delete
logs.requests.view
logs.requests.delete
logs.system.view
logs.system.delete
```

Without ACL, the package still requires an authenticated user by default. A custom gate can be configured:

```env
NUEWIRE_LOGS_GATE=manage-platform-logs
NUEWIRE_LOGS_GUARD=web
```

## Retention

Prune expired database logs manually:

```bash
php artisan nuewire:logs:prune
php artisan nuewire:logs:prune --audit-days=180 --request-days=14
```

Schedule pruning in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('nuewire:logs:prune')->dailyAt('02:30');
```

A retention value of `0` disables pruning for that log type.

## Publish

```bash
php artisan vendor:publish --tag=nuewire-logs-config
php artisan vendor:publish --tag=nuewire-logs-views
php artisan vendor:publish --tag=nuewire-logs-translations
```

Published paths:

```text
config/nuewire/logs.php
resources/views/vendor/nuewire/logs
lang/vendor/nuewire/logs
```

## Operational guidance

- Treat audit trails as append-only operational evidence; restrict deletion permissions.
- Log domain outcomes, not merely button clicks.
- Use explicit attribute allowlists and record both `old` and `attributes` only when they add investigative value.
- Avoid request payload capture unless it is necessary and legally justified.
- Apply retention based on security, privacy, incident-response, and regulatory requirements.
- For high traffic, move request-log persistence to an asynchronous pipeline or an observability backend rather than growing the primary application database without bounds.
