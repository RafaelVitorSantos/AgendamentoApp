<?php

namespace App\Core;

/**
 * Abstração de cache com suporte a arquivo e Redis.
 * Redis é usado automaticamente quando disponível e configurado.
 * Fallback transparente para arquivo em desenvolvimento ou se Redis falhar.
 */
class Cache
{
    private static ?self $instance = null;
    private ?\Redis $redis          = null;
    private bool    $redisAvailable = false;
    private string  $fileDir;
    private string  $prefix;

    private function __construct()
    {
        $this->fileDir = base_path('storage/cache/app');
        $this->prefix  = 'agendapro:';

        if (!is_dir($this->fileDir)) {
            mkdir($this->fileDir, 0755, true);
        }

        $this->connectRedis();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->redisAvailable) {
            $raw = $this->redis->get($this->prefix . $key);
            if ($raw === false) return $default;
            return unserialize($raw);
        }

        return $this->fileGet($key, $default);
    }

    public function set(string $key, mixed $value, int $ttl = 300): void
    {
        if ($this->redisAvailable) {
            $this->redis->setex($this->prefix . $key, $ttl, serialize($value));
            return;
        }

        $this->fileSet($key, $value, $ttl);
    }

    public function delete(string $key): void
    {
        if ($this->redisAvailable) {
            $this->redis->del($this->prefix . $key);
            return;
        }

        $file = $this->filePath($key);
        if (file_exists($file)) unlink($file);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Retorna valor do cache ou executa $callback e armazena o resultado.
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) return $cached;

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function isRedisAvailable(): bool
    {
        return $this->redisAvailable;
    }

    // ---------------------------------------------------
    // Redis
    // ---------------------------------------------------

    private function connectRedis(): void
    {
        $host = env('REDIS_HOST', '');
        if (!$host || !class_exists('\Redis')) {
            return;
        }

        try {
            $this->redis = new \Redis();
            $connected   = $this->redis->connect(
                $host,
                (int) env('REDIS_PORT', 6379),
                1.5
            );

            if (!$connected) {
                $this->redis = null;
                return;
            }

            $pass = env('REDIS_PASSWORD', '');
            if ($pass) $this->redis->auth($pass);

            $this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
            $this->redisAvailable = true;
        } catch (\Throwable) {
            $this->redis          = null;
            $this->redisAvailable = false;
        }
    }

    // ---------------------------------------------------
    // File cache (fallback)
    // ---------------------------------------------------

    private function fileGet(string $key, mixed $default): mixed
    {
        $file = $this->filePath($key);
        if (!file_exists($file)) return $default;

        $raw = json_decode(file_get_contents($file), true);
        if (!$raw || time() > $raw['expires_at']) {
            @unlink($file);
            return $default;
        }

        return unserialize($raw['value']);
    }

    private function fileSet(string $key, mixed $value, int $ttl): void
    {
        file_put_contents(
            $this->filePath($key),
            json_encode(['expires_at' => time() + $ttl, 'value' => serialize($value)]),
            LOCK_EX
        );
    }

    private function filePath(string $key): string
    {
        return $this->fileDir . '/' . md5($key) . '.cache';
    }
}
