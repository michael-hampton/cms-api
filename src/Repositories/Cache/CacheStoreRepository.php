<?php

namespace App\Repositories\Cache;

use App\Framework\Database\Database;

class CacheStoreRepository
{
    public function __construct(
        private readonly Database $database
    ) {
    }

    public function find(string $key): ?array
    {
        return $this->database->fetchOne(
            'SELECT `key`, `value`, `expires_at` FROM `cache_store` WHERE `key` = :key LIMIT 1',
            ['key' => $key]
        );
    }

    public function upsert(string $key, string $value, string $expiresAt): void
    {
        $now = date('Y-m-d H:i:s');

        $this->database->query(
            'INSERT INTO `cache_store` (`key`, `value`, `expires_at`, `created_at`, `updated_at`)
             VALUES (:key, :value, :expires_at, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                `value` = VALUES(`value`),
                `expires_at` = VALUES(`expires_at`),
                `updated_at` = VALUES(`updated_at`)',
            [
                'key' => $key,
                'value' => $value,
                'expires_at' => $expiresAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function delete(string $key): void
    {
        $this->database->query(
            'DELETE FROM `cache_store` WHERE `key` = :key',
            ['key' => $key]
        );
    }

    public function deleteMany(array $keys): int
    {
        $keys = array_values(array_unique(array_filter($keys, 'is_string')));

        if ($keys === []) {
            return 0;
        }

        $placeholders = [];
        $params = [];

        foreach ($keys as $index => $key) {
            $placeholder = "key_{$index}";
            $placeholders[] = ":{$placeholder}";
            $params[$placeholder] = $key;
        }

        $stmt = $this->database->query(
            'DELETE FROM `cache_store` WHERE `key` IN (' . implode(', ', $placeholders) . ')',
            $params
        );

        return $stmt->rowCount();
    }

    public function deleteExpired(string $now): int
    {
        $stmt = $this->database->query(
            'DELETE FROM `cache_store` WHERE `expires_at` <= :now',
            ['now' => $now]
        );

        return $stmt->rowCount();
    }

    public function deleteAll(): void
    {
        $this->database->query('DELETE FROM `cache_store`');
    }
}
