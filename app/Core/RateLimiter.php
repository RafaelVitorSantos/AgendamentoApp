<?php

namespace App\Core;

/**
 * Rate limiter baseado em arquivo.
 * Limita tentativas por chave dentro de uma janela de tempo.
 * Substitua por implementação Redis quando Redis estiver ativo em produção.
 */
class RateLimiter
{
    private string $storageDir;

    public function __construct()
    {
        $this->storageDir = base_path('storage/cache/rate_limits');
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Verifica se a chave excedeu o número máximo de tentativas na janela.
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $data = $this->getData($key);
        $this->evictExpired($data, $decaySeconds);
        return count($data['attempts']) >= $maxAttempts;
    }

    /**
     * Registra uma nova tentativa para a chave.
     */
    public function hit(string $key): void
    {
        $data = $this->getData($key);
        $data['attempts'][] = time();
        $this->saveData($key, $data);
    }

    /**
     * Retorna quantas tentativas restam antes do bloqueio.
     */
    public function remaining(string $key, int $maxAttempts, int $decaySeconds): int
    {
        $data = $this->getData($key);
        $this->evictExpired($data, $decaySeconds);
        return max(0, $maxAttempts - count($data['attempts']));
    }

    /**
     * Retorna em quantos segundos a chave estará disponível novamente.
     */
    public function availableIn(string $key, int $decaySeconds): int
    {
        $data = $this->getData($key);
        if (empty($data['attempts'])) {
            return 0;
        }
        $oldest = min($data['attempts']);
        return max(0, ($oldest + $decaySeconds) - time());
    }

    /**
     * Limpa o contador da chave (ex.: após login bem-sucedido).
     */
    public function clear(string $key): void
    {
        $file = $this->filePath($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    private function evictExpired(array &$data, int $decaySeconds): void
    {
        $cutoff = time() - $decaySeconds;
        $data['attempts'] = array_values(
            array_filter($data['attempts'], fn(int $t) => $t > $cutoff)
        );
    }

    private function getData(string $key): array
    {
        $file = $this->filePath($key);
        if (!file_exists($file)) {
            return ['attempts' => []];
        }
        $raw = file_get_contents($file);
        return json_decode($raw, true) ?? ['attempts' => []];
    }

    private function saveData(string $key, array $data): void
    {
        file_put_contents($this->filePath($key), json_encode($data), LOCK_EX);
    }

    private function filePath(string $key): string
    {
        return $this->storageDir . '/' . md5($key) . '.json';
    }
}
