<?php

namespace App\Jobs;

/**
 * Contrato base para todos os jobs assíncronos.
 */
abstract class BaseJob
{
    /** Fila padrão onde o job será publicado */
    public string $queue = 'default';

    /** Número máximo de tentativas antes de mover para failed_jobs */
    public int $maxAttempts = 3;

    /** Delay em segundos para a próxima tentativa após falha */
    public int $retryDelay = 60;

    /**
     * Executa o job. Lança exceção em caso de falha (será capturada pelo worker).
     */
    abstract public function handle(): void;

    /**
     * Serializa o job para gravação na tabela jobs (TEXT, JSON-envelope).
     * Usamos base64(serialize()) dentro de um JSON para manter compatibilidade
     * com a coluna TEXT e preservar objetos PHP sem perda de tipo.
     */
    public function serialize(): string
    {
        return json_encode([
            'class'   => static::class,
            'payload' => base64_encode(serialize($this)),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Reconstrói o job a partir do envelope JSON gravado no banco.
     */
    public static function fromRow(string $raw): BaseJob
    {
        $envelope = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $job = unserialize(base64_decode($envelope['payload']));
        if (!$job instanceof BaseJob) {
            throw new \UnexpectedValueException("Payload corrompido: classe {$envelope['class']}");
        }
        return $job;
    }
}
