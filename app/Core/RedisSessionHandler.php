<?php

namespace App\Core;

/**
 * Handler de sessão baseado em Redis.
 * Registrado via session_set_save_handler() no bootstrap quando Redis está disponível.
 * Permite escala horizontal (múltiplos servidores PHP compartilhando sessões).
 */
class RedisSessionHandler implements \SessionHandlerInterface
{
    private \Redis $redis;
    private int    $ttl;
    private string $prefix;

    public function __construct(\Redis $redis, int $ttlSeconds = 7200, string $prefix = 'agendapro_session:')
    {
        $this->redis  = $redis;
        $this->ttl    = $ttlSeconds;
        $this->prefix = $prefix;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $data = $this->redis->get($this->prefix . $id);
        return $data === false ? '' : $data;
    }

    public function write(string $id, string $data): bool
    {
        return $this->redis->setex($this->prefix . $id, $this->ttl, $data);
    }

    public function destroy(string $id): bool
    {
        $this->redis->del($this->prefix . $id);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        // Redis expira automaticamente via TTL — nada a fazer aqui.
        return 0;
    }
}
