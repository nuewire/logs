<?php

declare(strict_types=1);

return [
    'locale' => env('NUEWIRE_LOGS_LOCALE', 'id'),
    'supported_locales' => ['id', 'en'],
    'remember_locale' => (bool) env('NUEWIRE_LOGS_REMEMBER_LOCALE', true),
    'locale_session_key' => 'nuewire.logs.locale',

    'authorization' => [
        'require_authenticated_user' => (bool) env('NUEWIRE_LOGS_REQUIRE_AUTH', true),
        'gate' => env('NUEWIRE_LOGS_GATE'),
        'guard' => env('NUEWIRE_LOGS_GUARD'),
    ],

    'audit' => [
        'default_log_name' => env('NUEWIRE_AUDIT_LOG_NAME', 'platform'),
        'per_page' => (int) env('NUEWIRE_AUDIT_PER_PAGE', 25),
        'retention_days' => (int) env('NUEWIRE_AUDIT_RETENTION_DAYS', 365),
        'sensitive_keys' => [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'access_token',
            'refresh_token',
            'authorization',
            'cookie',
            'secret',
            'api_key',
            'card_number',
            'cvv',
        ],
        'redacted_value' => '[REDACTED]',
        'max_value_length' => 10000,
    ],

    'request' => [
        'enabled' => (bool) env('NUEWIRE_REQUEST_LOG_ENABLED', true),
        'auto_register_middleware' => (bool) env('NUEWIRE_REQUEST_LOG_AUTO_MIDDLEWARE', true),
        'connection' => env('NUEWIRE_REQUEST_LOG_DB_CONNECTION'),
        'table' => env('NUEWIRE_REQUEST_LOG_TABLE', 'nuewire_request_logs'),
        'per_page' => (int) env('NUEWIRE_REQUEST_LOG_PER_PAGE', 25),
        'retention_days' => (int) env('NUEWIRE_REQUEST_LOG_RETENTION_DAYS', 30),
        'slow_threshold_ms' => (int) env('NUEWIRE_REQUEST_LOG_SLOW_MS', 1000),
        'add_request_id_header' => true,
        'capture_query' => true,
        'capture_payload' => false,
        'capture_headers' => false,
        'header_allowlist' => [
            'accept',
            'content-type',
            'x-request-id',
            'x-forwarded-for',
        ],
        'sensitive_keys' => [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'access_token',
            'refresh_token',
            'authorization',
            'cookie',
            'secret',
            'api_key',
            'card_number',
            'cvv',
        ],
        'redacted_value' => '[REDACTED]',
        'max_value_length' => 2000,
        'except_methods' => ['OPTIONS'],
        'except_paths' => [
            'livewire/*',
            'telescope/*',
            'horizon/*',
        ],
        'except_route_names' => [],
        'report_failures' => false,
    ],

    'system' => [
        'paths' => [storage_path('logs')],
        'extensions' => ['log'],
        'tail_lines' => (int) env('NUEWIRE_SYSTEM_LOG_LINES', 500),
        'max_lines' => (int) env('NUEWIRE_SYSTEM_LOG_MAX_LINES', 2000),
    ],
];
