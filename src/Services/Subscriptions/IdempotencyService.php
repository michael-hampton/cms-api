<?php

namespace App\Services\Subscriptions;

use App\Framework\Database\Database;

class IdempotencyService
{
    private const EXPIRY_HOURS = 24;

    public function __construct(
        private readonly Database $database
    )
    {
    }

    /**
     * Check if an operation with this idempotency key has already been processed
     */
    public function checkKey(string $key, string $operation): ?array
    {
        $result = $this->database->query(
            "SELECT response_data, created_at FROM idempotency_keys 
             WHERE idempotency_key = ? AND operation = ? 
             AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)",
            [$key, $operation, self::EXPIRY_HOURS]
        );

        if (!empty($result)) {
            return json_decode($result[0]['response_data'], true);
        }

        return null;
    }

    /**
     * Store the result of an operation with an idempotency key
     */
    public function storeKey(string $key, string $operation, array $responseData): void
    {
        $this->database->execute(
            "INSERT INTO idempotency_keys (idempotency_key, operation, response_data, created_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE response_data = VALUES(response_data), created_at = VALUES(created_at)",
            [$key, $operation, json_encode($responseData)]
        );
    }

    /**
     * Generate an idempotency key from request parameters
     */
    public function generateKey(string $operation, array $params): string
    {
        ksort($params);
        return hash('sha256', $operation . ':' . json_encode($params));
    }
}