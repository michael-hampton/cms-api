<?php
namespace App\Services\PublicContent\Config;

interface PublicContentConfigSource
{
    /**
     * Whether a resolvable config document exists for this site.
     * File-backed sources are always resolvable.
     */
    public function has(int $siteId): bool;

    /**
     * Dot-notation lookup, mirroring the old config('public_content.{$key}') calls.
     */
    public function get(int $siteId, string $key, mixed $default = null): mixed;
}