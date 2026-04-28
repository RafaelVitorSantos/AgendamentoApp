<?php

/**
 * AgendaPRO SaaS — Bootstrap da aplicação.
 * Inicializa constantes, carrega .env, autoload e dependências.
 */

declare(strict_types=1);

// Caminho raiz do projeto
define('BASE_PATH', __DIR__);

// Carrega o .env
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}

// Autoload do Composer (obrigatório)
$composerAutoload = BASE_PATH . '/vendor/autoload.php';
if (!file_exists($composerAutoload)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Erro de configuração</h1><p>Execute na pasta do projeto: <code>composer install</code> ou <code>composer dump-autoload</code></p>';
    exit;
}
require_once $composerAutoload;

// Helpers globais
require_once BASE_PATH . '/app/Helpers/helpers.php';

// Configurações de PHP
$isDebug = env('APP_DEBUG', false);
error_reporting($isDebug ? E_ALL : E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', $isDebug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', storage_path('logs/php_errors.log'));

date_default_timezone_set(config('app.timezone', 'America/Sao_Paulo'));
mb_internal_encoding('UTF-8');

// Inicia sessão
if (session_status() === PHP_SESSION_NONE) {
    $sessionConfig = config('session');
    if ($sessionConfig) {
        $sessionTtl = (int)(($sessionConfig['lifetime'] ?? 120) * 60);

        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', $sessionConfig['samesite'] ?? 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) $sessionTtl);

        if (($sessionConfig['secure'] ?? false)) {
            ini_set('session.cookie_secure', '1');
        }

        // Usa Redis para sessões quando disponível (escala horizontal)
        $driver = env('SESSION_DRIVER', 'file');
        if ($driver === 'redis' && class_exists('\Redis')) {
            try {
                $redisCfg = $sessionConfig['redis'];
                $redis    = new \Redis();
                $redis->connect($redisCfg['host'], (int)$redisCfg['port'], 1.5);
                if ($redisCfg['password']) $redis->auth($redisCfg['password']);

                $handler = new \App\Core\RedisSessionHandler($redis, $sessionTtl, $redisCfg['prefix']);
                session_set_save_handler($handler, true);
            } catch (\Throwable $e) {
                // Fallback silencioso para driver de arquivo
                error_log('Redis session unavailable, using file driver: ' . $e->getMessage());
                session_save_path($sessionConfig['files_path'] ?? BASE_PATH . '/storage/sessions');
            }
        } else {
            session_save_path($sessionConfig['files_path'] ?? BASE_PATH . '/storage/sessions');
        }
    }
    session_start();
}

// Limpa flash data da requisição anterior
if (isset($_SESSION['_flash_used'])) {
    unset($_SESSION['_flash']);
    unset($_SESSION['_flash_used']);
}
if (isset($_SESSION['_flash'])) {
    $_SESSION['_flash_used'] = true;
}

// Limpa old input
unset($_SESSION['_old_input']);
