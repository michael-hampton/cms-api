<?php
namespace App\Services\PublicContent\Theming;

interface PublicContentDesignTokenSource
{
    public function has(int $siteId): bool;

    /** @return array<string, mixed> */
    public function defaults(int $siteId): array;

    /** @return array<string, mixed> */
    public function overrides(int $siteId): array;
}