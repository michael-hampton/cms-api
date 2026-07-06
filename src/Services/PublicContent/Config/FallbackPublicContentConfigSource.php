<?php
namespace App\Services\PublicContent\Config;

/**
 * Decorator: database is authoritative once a document exists for the
 * site; if it doesn't exist yet (not migrated), falls back to file config.
 * This is the kill-switch behaviour requested — it's a per-site fallback,
 * not a per-missing-key fallback (a key genuinely absent from an existing
 * document just resolves to the caller's default).
 */
final class FallbackPublicContentConfigSource implements PublicContentConfigSource
{
    public function __construct(
        private readonly PublicContentConfigSource $database,
        private readonly PublicContentConfigSource $file,
    ) {
    }

    public function has(int $siteId): bool
    {
        return true;
    }

    public function get(int $siteId, string $key, mixed $default = null): mixed
    {
        $source = $this->database->has($siteId) ? $this->database : $this->file;

        return $source->get($siteId, $key, $default);
    }
}