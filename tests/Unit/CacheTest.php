<?php

namespace Tests\Unit;

use App\Core\Cache;
use Tests\TestCase;

/**
 * Testes da abstração de Cache (driver de arquivo).
 * Puro — sem banco de dados.
 */
class CacheTest extends TestCase
{
    private Cache  $cache;
    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache  = Cache::getInstance();
        $this->prefix = 'test_cache_' . uniqid() . '_';
    }

    protected function tearDown(): void
    {
        // Limpa chaves criadas neste teste
        foreach (['str', 'int', 'arr', 'remember', 'ttl'] as $suffix) {
            $this->cache->delete($this->prefix . $suffix);
        }
        parent::tearDown();
    }

    public function test_set_e_get_string(): void
    {
        $this->cache->set($this->prefix . 'str', 'hello world', 60);
        $this->assertEquals('hello world', $this->cache->get($this->prefix . 'str'));
    }

    public function test_set_e_get_inteiro(): void
    {
        $this->cache->set($this->prefix . 'int', 42, 60);
        $this->assertSame(42, $this->cache->get($this->prefix . 'int'));
    }

    public function test_set_e_get_array(): void
    {
        $data = ['id' => 1, 'name' => 'Teste', 'nested' => [1, 2, 3]];
        $this->cache->set($this->prefix . 'arr', $data, 60);
        $this->assertEquals($data, $this->cache->get($this->prefix . 'arr'));
    }

    public function test_get_retorna_default_quando_chave_nao_existe(): void
    {
        $result = $this->cache->get('chave_que_nao_existe_' . uniqid(), 'default_value');
        $this->assertEquals('default_value', $result);
    }

    public function test_get_retorna_null_quando_sem_default(): void
    {
        $this->assertNull($this->cache->get('chave_inexistente_' . uniqid()));
    }

    public function test_has_retorna_true_para_chave_existente(): void
    {
        $this->cache->set($this->prefix . 'str', 'valor', 60);
        $this->assertTrue($this->cache->has($this->prefix . 'str'));
    }

    public function test_has_retorna_false_para_chave_inexistente(): void
    {
        $this->assertFalse($this->cache->has('inexistente_' . uniqid()));
    }

    public function test_delete_remove_chave(): void
    {
        $this->cache->set($this->prefix . 'str', 'valor', 60);
        $this->assertTrue($this->cache->has($this->prefix . 'str'));

        $this->cache->delete($this->prefix . 'str');
        $this->assertFalse($this->cache->has($this->prefix . 'str'));
    }

    public function test_remember_retorna_valor_em_cache(): void
    {
        $this->cache->set($this->prefix . 'remember', 'cached', 60);

        $callCount = 0;
        $result = $this->cache->remember($this->prefix . 'remember', 60, function () use (&$callCount) {
            $callCount++;
            return 'fresh';
        });

        $this->assertEquals('cached', $result);
        $this->assertEquals(0, $callCount, 'Callback não deve ser chamado quando há cache');
    }

    public function test_remember_executa_callback_quando_sem_cache(): void
    {
        $callCount = 0;
        $result = $this->cache->remember($this->prefix . 'remember', 60, function () use (&$callCount) {
            $callCount++;
            return 'computed';
        });

        $this->assertEquals('computed', $result);
        $this->assertEquals(1, $callCount);
    }

    public function test_remember_armazena_resultado_do_callback(): void
    {
        $this->cache->remember($this->prefix . 'remember', 60, fn() => 'stored_value');

        // Segunda chamada deve usar cache, não chamar callback
        $callCount = 0;
        $result    = $this->cache->remember($this->prefix . 'remember', 60, function () use (&$callCount) {
            $callCount++;
            return 'should_not_be_called';
        });

        $this->assertEquals('stored_value', $result);
        $this->assertEquals(0, $callCount);
    }

    public function test_cache_expira_apos_ttl(): void
    {
        $key = $this->prefix . 'ttl';

        // Escreve diretamente com timestamp expirado no arquivo
        $cacheDir = BASE_PATH . '/storage/cache/app';
        $file     = $cacheDir . '/' . md5($key) . '.cache';
        file_put_contents($file, json_encode([
            'expires_at' => time() - 1,
            'value'      => serialize('expired_value'),
        ]), LOCK_EX);

        $this->assertNull($this->cache->get($key));
        $this->assertFalse($this->cache->has($key));
    }
}
