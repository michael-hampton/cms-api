<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Avoids ~150–200ms bcrypt work on every factory/user seed in functional suites.
 *
 * Transactional isolation rolls back users created inside tests, so reseeding
 * with password_hash() on every setUp dominated suite time.
 */
final class TestPassword
{
    public const PLAIN = 'password';

    /**
     * Precomputed bcrypt hash of {@see PLAIN} (PASSWORD_BCRYPT).
     * Verified with password_verify(PLAIN, HASH).
     */
    public const HASH = '$2y$12$QrQB3xKU7/elpYKJ1By0Z.lKOtY.PN/bP9Sjayqg7RWqP.DJ9LsE6';

    /** @var array<string, string> */
    private static array $cache = [
        self::PLAIN => self::HASH,
    ];

    public static function hash(string $plain = self::PLAIN): string
    {
        return self::$cache[$plain] ??= password_hash($plain, PASSWORD_BCRYPT);
    }
}
