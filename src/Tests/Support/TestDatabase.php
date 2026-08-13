<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Framework\Database\Database;
use PDO;
use RuntimeException;

/**
 * Resolves and hard-guards the PHPUnit MySQL database.
 *
 * Production uses `mydb`. Functional tests must only ever touch a dedicated
 * test database (default `test_db`). This helper refuses anything that does
 * not look like a test database before a connection is used.
 */
final class TestDatabase
{
    private const DEFAULT_NAME = 'test_db';

    /** @var list<string> */
    private const FORBIDDEN_NAMES = [
        'mydb',
        'mysql',
        'information_schema',
        'performance_schema',
        'sys',
        'production',
        'prod',
    ];

    public static function config(): array
    {
        $config = [
            'driver' => 'mysql',
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_DB_PORT') ?: '3306',
            'database' => getenv('TEST_DB_NAME') ?: self::DEFAULT_NAME,
            'username' => getenv('TEST_DB_USER') ?: 'root',
            'password' => getenv('TEST_DB_PASS') ?: 'rootsecret',
            'charset' => 'utf8mb4',
        ];

        self::assertSafeConfig($config);

        return $config;
    }

    public static function connect(bool $reuseExisting = true): Database
    {
        $config = self::config();

        if (!$reuseExisting) {
            Database::resetInstance();
        }

        try {
            $database = Database::getInstance($config);
        } catch (\RuntimeException $e) {
            Database::resetInstance();
            $database = Database::getInstance($config);
        }

        self::assertConnectedToTestDatabase($database, $config['database']);

        return $database;
    }

    public static function assertSafeConfig(array $config): void
    {
        $name = strtolower(trim((string) ($config['database'] ?? '')));

        if ($name === '') {
            throw new RuntimeException('Test database name is empty; refusing to connect.');
        }

        if (in_array($name, self::FORBIDDEN_NAMES, true)) {
            throw new RuntimeException(
                "Refusing to use database [{$name}] for tests — only a dedicated test database is allowed."
            );
        }

        if (!str_contains($name, 'test')) {
            throw new RuntimeException(
                "Refusing to use database [{$name}] for tests — name must contain \"test\" (e.g. test_db)."
            );
        }

        $appDatabase = strtolower(trim((string) (getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? ''))));
        if ($appDatabase !== '' && $appDatabase === $name && !str_contains($appDatabase, 'test')) {
            throw new RuntimeException(
                "Refusing to use database [{$name}] for tests — it matches the application DB_DATABASE."
            );
        }
    }

    public static function assertConnectedToTestDatabase(Database $database, ?string $expectedName = null): void
    {
        $info = $database->getConnectionInfo();
        $configured = strtolower((string) ($info['database'] ?? ''));
        $expected = strtolower($expectedName ?? (getenv('TEST_DB_NAME') ?: self::DEFAULT_NAME));

        self::assertSafeConfig(['database' => $configured]);

        if ($configured !== $expected) {
            throw new RuntimeException(
                "Test database mismatch: configured [{$configured}] but expected [{$expected}]."
            );
        }

        $stmt = $database->getConnection()->query('SELECT DATABASE() AS current_db');
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $actual = strtolower((string) ($row['current_db'] ?? ''));

        if ($actual === '' || $actual !== $expected) {
            throw new RuntimeException(
                "Refusing to continue: PDO is connected to [{$actual}], expected test database [{$expected}]."
            );
        }
    }
}
