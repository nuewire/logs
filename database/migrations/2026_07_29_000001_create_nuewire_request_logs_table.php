<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = trim((string) config('nuewire.logs.request.connection', ''));
        $schema = Schema::connection($connection !== '' ? $connection : null);
        $tableName = (string) config('nuewire.logs.request.table', 'nuewire_request_logs');

        if ($schema->hasTable($tableName)) {
            return;
        }

        $schema->create($tableName, function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('request_id')->unique();
            $table->string('method', 12)->index();
            $table->text('path');
            $table->string('route_name')->nullable()->index();
            $table->unsignedSmallInteger('status_code')->index();
            $table->unsignedInteger('duration_ms')->default(0)->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('user_type')->nullable();
            $table->string('user_id')->nullable();
            $table->json('query')->nullable();
            $table->json('payload')->nullable();
            $table->json('headers')->nullable();
            $table->string('exception_class')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['user_type', 'user_id']);
        });
    }

    public function down(): void
    {
        $connection = trim((string) config('nuewire.logs.request.connection', ''));
        Schema::connection($connection !== '' ? $connection : null)
            ->dropIfExists((string) config('nuewire.logs.request.table', 'nuewire_request_logs'));
    }
};
