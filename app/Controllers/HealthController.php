<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * Endpoint de health check para load balancers e monitoramento externo.
 * GET /health — retorna JSON com status de cada dependência.
 */
class HealthController extends Controller
{
    public function check(): void
    {
        $checks  = [];
        $healthy = true;

        // Banco de dados
        try {
            $db   = Database::getInstance();
            $stmt = $db->query('SELECT 1');
            $checks['database'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error', 'detail' => 'connection failed'];
            $healthy = false;
        }

        // Storage gravável
        $storagePath = BASE_PATH . '/storage';
        $storageOk   = is_dir($storagePath) && is_writable($storagePath);
        $checks['storage'] = ['status' => $storageOk ? 'ok' : 'error'];
        if (!$storageOk) $healthy = false;

        // Sessões
        $sessionsPath = BASE_PATH . '/storage/sessions';
        $sessionsOk   = is_dir($sessionsPath) && is_writable($sessionsPath);
        $checks['sessions'] = ['status' => $sessionsOk ? 'ok' : 'error'];
        if (!$sessionsOk) $healthy = false;

        // Redis (opcional — não derruba o health check se ausente)
        $checks['redis'] = $this->checkRedis();

        $status = $healthy ? 'healthy' : 'degraded';
        $code   = $healthy ? 200 : 503;

        $this->json([
            'status'     => $status,
            'timestamp'  => date('c'),
            'version'    => config('app.version', '1.0.0'),
            'checks'     => $checks,
        ], $code);
    }

    private function checkRedis(): array
    {
        $host = env('REDIS_HOST', '');
        if (!$host || !class_exists('\Redis')) {
            return ['status' => 'unavailable', 'detail' => 'not configured'];
        }

        try {
            $redis = new \Redis();
            $redis->connect(
                env('REDIS_HOST', '127.0.0.1'),
                (int) env('REDIS_PORT', 6379),
                1.0
            );
            $pass = env('REDIS_PASSWORD', '');
            if ($pass) $redis->auth($pass);
            $redis->ping();
            $redis->close();
            return ['status' => 'ok'];
        } catch (\Throwable $e) {
            return ['status' => 'unavailable', 'detail' => 'connection failed'];
        }
    }
}
