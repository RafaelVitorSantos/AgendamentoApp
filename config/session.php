<?php

/**
 * Configuração de sessão.
 */

return [
    'driver'   => env('SESSION_DRIVER', 'file'),  // file | redis
    'lifetime' => env('SESSION_LIFETIME', 120),     // minutos
    'path'     => '/',
    'domain'   => null,
    'secure'   => env('APP_ENV') === 'production',
    'httponly'  => true,
    'samesite'  => 'Strict',

    'files_path' => BASE_PATH . '/storage/sessions',

    'redis' => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'port'     => env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', ''),
        'prefix'   => 'agendapro_session:',
    ],
];
