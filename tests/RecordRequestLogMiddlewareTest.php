<?php

declare(strict_types=1);

namespace Nuewire\Logs\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Nuewire\Logs\Http\Middleware\RecordRequestLog;
use Nuewire\Logs\Models\RequestLog;
use Symfony\Component\HttpFoundation\Response;

final class RecordRequestLogMiddlewareTest extends TestCase
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

    public function test_request_id_is_available_during_the_request_and_returned_on_the_response(): void
    {
        $request = Request::create('/health', 'GET');
        $seenDuringRequest = null;

        $response = $this->app->make(RecordRequestLog::class)->handle(
            $request,
            static function (Request $request) use (&$seenDuringRequest): Response {
                $seenDuringRequest = $request->header('X-Request-Id');

                return new Response('ok', 200);
            },
        );

        self::assertIsString($seenDuringRequest);
        self::assertSame($seenDuringRequest, $request->attributes->get('nuewire_request_id'));
        self::assertSame($seenDuringRequest, $response->headers->get('X-Request-Id'));
        self::assertSame($seenDuringRequest, RequestLog::query()->value('request_id'));
    }
}
