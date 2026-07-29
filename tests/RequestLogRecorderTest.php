<?php

declare(strict_types=1);

namespace Nuewire\Logs\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Nuewire\Logs\Models\RequestLog;
use Nuewire\Logs\Support\RequestLogRecorder;
use Symfony\Component\HttpFoundation\Response;

final class RequestLogRecorderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('nuewire_request_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('request_id')->unique();
            $table->string('method', 12);
            $table->text('path');
            $table->string('route_name')->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('duration_ms');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('user_type')->nullable();
            $table->string('user_id')->nullable();
            $table->json('query')->nullable();
            $table->json('payload')->nullable();
            $table->json('headers')->nullable();
            $table->string('exception_class')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function test_recorder_stores_sanitized_request_metadata(): void
    {
        config()->set('nuewire.logs.request.capture_payload', true);
        $request = Request::create('/api/orders?filter=open', 'POST', [
            'name' => 'Example',
            'password' => 'secret',
        ], [], [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'PHPUnit']);

        $recorder = $this->app->make(RequestLogRecorder::class);
        self::assertTrue($recorder->shouldRecord($request));
        $recorder->record($request, new Response('', 201), '123e4567-e89b-12d3-a456-426614174000', 42);

        $log = RequestLog::query()->firstOrFail();
        self::assertSame('POST', $log->method);
        self::assertSame('/api/orders', $log->path);
        self::assertSame(201, $log->status_code);
        self::assertSame('open', $log->query['filter']);
        self::assertSame('[REDACTED]', $log->payload['password']);
    }
}
