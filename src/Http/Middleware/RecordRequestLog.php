<?php

declare(strict_types=1);

namespace Nuewire\Logs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Nuewire\Logs\Support\RequestLogRecorder;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RecordRequestLog
{
    public function __construct(private readonly RequestLogRecorder $recorder)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $requestId = $this->requestId($request);
        $response = null;
        $exception = null;

        $request->headers->set('X-Request-Id', $requestId);
        $request->attributes->set('nuewire_request_id', $requestId);

        try {
            $response = $next($request);

            if ((bool) config('nuewire.logs.request.add_request_id_header', true)) {
                $response->headers->set('X-Request-Id', $requestId);
            }

            return $response;
        } catch (Throwable $throwable) {
            $exception = $throwable;
            throw $throwable;
        } finally {
            try {
                if ($this->recorder->shouldRecord($request)) {
                    $duration = (int) round((hrtime(true) - $startedAt) / 1_000_000);
                    $this->recorder->record($request, $response, $requestId, $duration, $exception);
                }
            } catch (Throwable $loggingFailure) {
                if ((bool) config('nuewire.logs.request.report_failures', false)) {
                    report($loggingFailure);
                }
            }
        }
    }

    private function requestId(Request $request): string
    {
        $candidate = trim((string) $request->headers->get('X-Request-Id', ''));

        if ($candidate !== '' && Str::isUuid($candidate)) {
            return $candidate;
        }

        return (string) Str::uuid();
    }
}
