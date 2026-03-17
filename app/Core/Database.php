<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Singleton de conexão com o banco de dados.
 * Usa PDO com prepared statements obrigatórios.
 */
class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = config('database');

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    $config['options']
                );
            } catch (PDOException $e) {
                if (config('app.debug')) {
                    throw $e;
                }
                error_log('Database connection failed: ' . $e->getMessage());
                throw new \RuntimeException('Erro ao conectar ao banco de dados.');
            }
        }

        return self::$instance;
    }

    public static function closeConnection(): void
    {
        self::$instance = null;
    }
}
