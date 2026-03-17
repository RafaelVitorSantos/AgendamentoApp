<?php

/**
 * Configuração de autenticação e JWT.
 */

return [
    'password' => [
        'algo'        => PASSWORD_ARGON2ID,
        'memory_cost' => 65536,
        'time_cost'   => 4,
        'threads'     => 3,
    ],

    'jwt' => [
        'secret'      => env('JWT_SECRET', ''),
        'algorithm'   => 'HS256',
        'ttl'         => (int) env('JWT_TTL', 3600),
        'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 604800),
    ],

    'csrf' => [
        'token_name' => '_csrf_token',
        'header'     => 'X-CSRF-TOKEN',
    ],
];
