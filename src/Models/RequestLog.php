<?php

declare(strict_types=1);

namespace Nuewire\Logs\Models;

use Illuminate\Database\Eloquent\Model;

final class RequestLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $connection = trim((string) config('nuewire.logs.request.connection', ''));

        if ($connection !== '') {
            $this->setConnection($connection);
        }

        $this->setTable((string) config('nuewire.logs.request.table', 'nuewire_request_logs'));
    }

    protected function casts(): array
    {
        return [
            'query' => 'array',
            'payload' => 'array',
            'headers' => 'array',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
