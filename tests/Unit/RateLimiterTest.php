<?php

namespace Tests\Unit;

use App\Core\RateLimiter;
use Tests\TestCase;

/**
 * S2-04 — Testes do RateLimiter.
 * Puro — sem banco de dados.
 */
class RateLimiterTest extends TestCase
{
    private RateLimiter $limiter;
    private string      $testKey;
    private string      $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->limiter  = new RateLimiter();
        $this->testKey  = 'test:rate_limit:' . uniqid();
        $this->cacheDir = BASE_PATH . '/storage/cache/rate_limits';
    }

    protected function tearDown(): void
    {
        $this->limiter->clear($this->testKey);
        parent::tearDown();
    }

    public function test_permite_tentativas_dentro_do_limite(): void
    {
        $this->assertFalse(
            $this->limiter->tooManyAttempts($this->testKey, maxAttempts: 3, decaySeconds: 60)
        );
    }

    public function test_bloqueia_apos_limite_atingido(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->hit($this->testKey);
        }

        $this->assertTrue(
            $this->limiter->tooManyAttempts($this->testKey, maxAttempts: 3, decaySeconds: 60)
        );
    }

    public function test_remaining_decrementa_corretamente(): void
    {
        $this->assertEquals(3, $this->limiter->remaining($this->testKey, 3, 60));

        $this->limiter->hit($this->testKey);
        $this->assertEquals(2, $this->limiter->remaining($this->testKey, 3, 60));

        $this->limiter->hit($this->testKey);
        $this->assertEquals(1, $this->limiter->remaining($this->testKey, 3, 60));
    }

    public function test_remaining_nao_vai_abaixo_de_zero(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->limiter->hit($this->testKey);
        }

        $this->assertEquals(0, $this->limiter->remaining($this->testKey, 3, 60));
    }

    public function test_clear_reseta_contador(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->hit($this->testKey);
        }
        $this->assertTrue($this->limiter->tooManyAttempts($this->testKey, 3, 60));

        $this->limiter->clear($this->testKey);

        $this->assertFalse($this->limiter->tooManyAttempts($this->testKey, 3, 60));
    }

    public function test_available_in_retorna_tempo_de_espera(): void
    {
        $this->limiter->hit($this->testKey);

        $availableIn = $this->limiter->availableIn($this->testKey, decaySeconds: 60);

        $this->assertGreaterThan(0, $availableIn);
        $this->assertLessThanOrEqual(60, $availableIn);
    }

    public function test_available_in_retorna_zero_sem_tentativas(): void
    {
        $this->assertEquals(0, $this->limiter->availableIn($this->testKey, 60));
    }

    public function test_tentativas_expiradas_sao_ignoradas(): void
    {
        // Simula tentativa registrada com timestamp antigo (2 segundos atrás, janela de 1 segundo)
        $file = $this->cacheDir . '/' . md5($this->testKey) . '.json';
        file_put_contents($file, json_encode([
            'attempts' => [time() - 2],
        ]), LOCK_EX);

        // Com decaySeconds=1, a tentativa de 2s atrás deve ter expirado
        $this->assertFalse(
            $this->limiter->tooManyAttempts($this->testKey, maxAttempts: 1, decaySeconds: 1)
        );
    }

    public function test_chaves_diferentes_sao_independentes(): void
    {
        $keyA = 'test:rate:key_a:' . uniqid();
        $keyB = 'test:rate:key_b:' . uniqid();

        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit($keyA);
        }

        $this->assertTrue($this->limiter->tooManyAttempts($keyA, 5, 60));
        $this->assertFalse($this->limiter->tooManyAttempts($keyB, 5, 60));

        $this->limiter->clear($keyA);
        $this->limiter->clear($keyB);
    }
}
