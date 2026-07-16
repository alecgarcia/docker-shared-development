<?php

$reverbAppsFromJson = (static function (): array {
    $path = base_path('apps.json');

    if (! is_file($path)) {
        throw new RuntimeException(
            "Reverb host requires apps.json at [{$path}]. Copy apps.json.example to apps.json and set unique keys per Laravel project."
        );
    }

    try {
        $decoded = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException("Invalid JSON in apps.json: {$e->getMessage()}", 0, $e);
    }

    if (! is_array($decoded)) {
        throw new RuntimeException('apps.json must contain a JSON array of application objects.');
    }

    $defaults = [
        'allowed_origins' => ['*'],
        'ping_interval' => env('REVERB_APP_PING_INTERVAL', 60),
        'activity_timeout' => env('REVERB_APP_ACTIVITY_TIMEOUT', 30),
        'max_connections' => env('REVERB_APP_MAX_CONNECTIONS'),
        'max_message_size' => env('REVERB_APP_MAX_MESSAGE_SIZE', 10_000),
        'accept_client_events_from' => env('REVERB_APP_ACCEPT_CLIENT_EVENTS_FROM', 'members'),
        'rate_limiting' => [
            'enabled' => env('REVERB_APP_RATE_LIMITING_ENABLED', false),
            'max_attempts' => env('REVERB_APP_RATE_LIMIT_MAX_ATTEMPTS', 60),
            'decay_seconds' => env('REVERB_APP_RATE_LIMIT_DECAY_SECONDS', 60),
            'terminate_on_limit' => env('REVERB_APP_RATE_LIMIT_TERMINATE', false),
        ],
    ];

    foreach ($decoded as $index => $app) {
        if (! is_array($app)) {
            throw new RuntimeException("apps.json entry #{$index} must be an object.");
        }
        foreach (['app_id', 'key', 'secret'] as $field) {
            if (empty($app[$field])) {
                throw new RuntimeException("apps.json entry #{$index} is missing required field \"{$field}\".");
            }
        }
        $decoded[$index] = array_replace_recursive($defaults, $app);
    }

    return $decoded;
})();

return [

    /*
    |--------------------------------------------------------------------------
    | Default Reverb Server
    |--------------------------------------------------------------------------
    |
    | This option controls the default server used by Reverb to handle
    | incoming messages as well as broadcasting message to all your
    | connected clients. At this time only "reverb" is supported.
    |
    */

    'default' => env('REVERB_SERVER', 'reverb'),

    /*
    |--------------------------------------------------------------------------
    | Reverb Servers
    |--------------------------------------------------------------------------
    |
    | Here you may define details for each of the supported Reverb servers.
    | Each server has its own configuration options that are defined in
    | the array below. You should ensure all the options are present.
    |
    */

    'servers' => [

        'reverb' => [
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port' => env('REVERB_SERVER_PORT', 8080),
            'path' => env('REVERB_SERVER_PATH', ''),
            'hostname' => env('REVERB_HOST'),
            'options' => [
                'tls' => [],
            ],
            'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10_000),
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
                'server' => [
                    'url' => env('REDIS_URL'),
                    'host' => env('REDIS_HOST', '127.0.0.1'),
                    'port' => env('REDIS_PORT', '6379'),
                    'username' => env('REDIS_USERNAME'),
                    'password' => env('REDIS_PASSWORD'),
                    'database' => env('REDIS_DB', '0'),
                    'timeout' => env('REDIS_TIMEOUT', 60),
                ],
            ],
            'pulse_ingest_interval' => env('REVERB_PULSE_INGEST_INTERVAL', 15),
            'telescope_ingest_interval' => env('REVERB_TELESCOPE_INGEST_INTERVAL', 15),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Reverb Applications
    |--------------------------------------------------------------------------
    |
    | Here you may define how Reverb applications are managed. If you choose
    | to use the "config" provider, you may define an array of apps which
    | your server will support, including their connection credentials.
    |
    */

    'apps' => [

        'provider' => 'config',

        'apps' => $reverbAppsFromJson,

    ],

];
