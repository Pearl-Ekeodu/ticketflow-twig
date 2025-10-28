<?php

namespace App\Models;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;
    private static array $config;

    public static function init(): void
    {
        self::$config = require __DIR__ . '/../../config/app.php';
    }

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::init();
            self::connect();
        }
        return self::$connection;
    }

    private static function connect(): void
    {
        try {
            $dbConfig = self::$config['database'];
            
            if ($dbConfig['driver'] === 'sqlite') {
                // Ensure database directory exists
                $dbDir = dirname($dbConfig['database']);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }
                
                self::$connection = new PDO(
                    'sqlite:' . $dbConfig['database'],
                    null,
                    null,
                    $dbConfig['options']
                );
            } else {
                // MySQL/PostgreSQL connection
                $dsn = sprintf(
                    '%s:host=%s;dbname=%s;charset=utf8mb4',
                    $dbConfig['driver'],
                    $dbConfig['host'] ?? 'localhost',
                    $dbConfig['database']
                );
                
                self::$connection = new PDO(
                    $dsn,
                    $dbConfig['username'],
                    $dbConfig['password'],
                    $dbConfig['options']
                );
            }
            
            // Initialize database schema if it doesn't exist
            self::initializeSchema();
            
        } catch (PDOException $e) {
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    private static function initializeSchema(): void
    {
        $schemaFile = __DIR__ . '/../../database/schema.sql';
        if (file_exists($schemaFile)) {
            $schema = file_get_contents($schemaFile);
            self::$connection->exec($schema);
        }
    }

    public static function close(): void
    {
        self::$connection = null;
    }
}
