<?php

namespace App\Services\PublicContent\Config;

/**
 * Database is preferred when a site document exists. Keys missing from that
 * document fall through to the file defaults so new widget eligibility keys
 * (e.g. widgets.recirculation.page_types) do not fail-open to ['*'].
 *
 * Sites with no document at all use the file source entirely.
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
        if (!$this->database->has($siteId)) {
            return $this->file->get($siteId, $key, $default);
        }

        $missing = new \stdClass();
        $value = $this->database->get($siteId, $key, $missing);

        if ($value === $missing) {
            return $this->file->get($siteId, $key, $default);
        }

        return $value;
    }
}
