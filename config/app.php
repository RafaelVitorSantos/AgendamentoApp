<?php

/**
 * Configurações gerais da aplicação.
 * Valores são carregados do .env com fallback para os defaults.
 */

return [
    'name'  => env('APP_NAME', 'AgendaPRO'),
    'env'   => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', false),
    'url'   => env('APP_URL', 'http://localhost'),
    'key'   => env('APP_KEY', ''),

    'timezone' => 'America/Sao_Paulo',
    'locale'   => 'pt_BR',
    'charset'  => 'UTF-8',

    'version' => '1.0.0',
];
