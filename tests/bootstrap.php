<?php

/**
 * Bootstrap de testes do AgendaPRO.
 * Carrega o ambiente de teste sem iniciar sessão HTTP real.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Carrega .env.testing primeiro; cai no .env padrão se não existir
$envFile = BASE_PATH . '/.env.testing';
if (!file_exists($envFile)) {
    $envFile = BASE_PATH . '/.env';
}

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $key = trim($key); $value = trim($value);
    if (!array_key_exists($key, $_ENV)) {
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/app/Helpers/helpers.php';

// Simula sessão sem iniciar sessão HTTP real
if (!isset($_SESSION)) {
    $_SESSION = [];
}

date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

// Garante diretórios de storage para testes
foreach (['logs', 'cache/app', 'cache/rate_limits', 'cache/plan_limits'] as $dir) {
    $path = BASE_PATH . '/storage/' . $dir;
    if (!is_dir($path)) mkdir($path, 0755, true);
}
