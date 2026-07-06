<?php
namespace App\Services\PublicContent\Config;

/**
 * The one place this migration allows a direct config() read — this class
 * *is* the file-backed adapter, so it owns that infrastructure boundary.
 * Everything else depends on the PublicContentConfigSource interface.
 */
final class FilePublicContentConfigSource implements PublicContentConfigSource
{
    private const string CONFIG_ROOT = 'public_content';

    public function has(int $siteId): bool
    {
        return true;
    }

    public function get(int $siteId, string $key, mixed $default = null): mixed
    {
        return config(self::CONFIG_ROOT . '.' . $key, $default);
    }
}