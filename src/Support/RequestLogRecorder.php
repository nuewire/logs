<?php

declare(strict_types=1);

namespace Nuewire\Logs\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Nuewire\Logs\Models\RequestLog;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RequestLogRecorder
{
    private ?bool $tableReady = null;

    public function shouldRecord(Request $request): bool
    {
        if (! (bool) config('nuewire.logs.request.enabled', true)) {
            return false;
        }

        if (in_array(strtoupper($request->method()), (array) config('nuewire.logs.request.except_methods', []), true)) {
            return false;
        }

        foreach ((array) config('nuewire.logs.request.except_paths', []) as $pattern) {
            if (is_string($pattern) && $pattern !== '' && $request->is($pattern)) {
                return false;
            }
        }

        $route = $request->route();
        $routeName = is_object($route) && method_exists($route, 'getName') ? $route->getName() : null;

        if (is_string($routeName) && in_array($routeName, (array) config('nuewire.logs.request.except_route_names', []), true)) {
            return false;
        }

        if ($routeName === 'nuewire.platform.page' && in_array((string) $request->route('page'), [
            'audit-trails',
            'request-logs',
            'system-logs',
        ], true)) {
            return false;
        }

        return $this->tableExists();
    }

    public function record(
        Request $request,
        ?Response $response,
        string $requestId,
        int $durationMs,
        ?Throwable $exception = null,
    ): void {
        $redactor = new SensitiveDataRedactor(
            array_values(array_filter((array) config('nuewire.logs.request.sensitive_keys', []), 'is_string')),
            (string) config('nuewire.logs.request.redacted_value', '[REDACTED]'),
            (int) config('nuewire.logs.request.max_value_length', 2000),
        );

        $route = $request->route();
        $routeName = is_object($route) && method_exists($route, 'getName') ? $route->getName() : null;
        $user = $request->user();

        RequestLog::query()->create([
            'request_id' => $requestId,
            'method' => strtoupper($request->method()),
            'path' => '/'.ltrim($request->path(), '/'),
            'route_name' => is_string($routeName) ? $routeName : null,
            'status_code' => $response?->getStatusCode() ?? 500,
            'duration_ms' => max(0, $durationMs),
            'ip_address' => $request->ip(),
            'user_agent' => $redactor->redact($request->userAgent()),
            'user_type' => $user instanceof Authenticatable ? $user::class : null,
            'user_id' => $user instanceof Authenticatable ? (string) $user->getAuthIdentifier() : null,
            'query' => (bool) config('nuewire.logs.request.capture_query', true)
                ? $redactor->redact($request->query())
                : null,
            'payload' => (bool) config('nuewire.logs.request.capture_payload', false)
                ? $redactor->redact($request->request->all())
                : null,
            'headers' => (bool) config('nuewire.logs.request.capture_headers', false)
                ? $this->allowedHeaders($request, $redactor)
                : null,
            'exception_class' => $exception !== null ? $exception::class : null,
            'created_at' => now(),
        ]);
    }

    private function tableExists(): bool
    {
        if ($this->tableReady === true) {
            return true;
        }

        try {
            $model = new RequestLog();
            $this->tableReady = Schema::connection($model->getConnectionName())->hasTable($model->getTable());
        } catch (Throwable) {
            $this->tableReady = false;
        }

        return $this->tableReady;
    }

    /** @return array<string, mixed> */
    private function allowedHeaders(Request $request, SensitiveDataRedactor $redactor): array
    {
        $headers = [];

        foreach ((array) config('nuewire.logs.request.header_allowlist', []) as $name) {
            if (! is_string($name) || $name === '' || ! $request->headers->has($name)) {
                continue;
            }

            $headers[strtolower($name)] = $redactor->redact($request->headers->all($name), $name);
        }

        return $headers;
    }
}
