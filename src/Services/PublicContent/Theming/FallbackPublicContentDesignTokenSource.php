<?php
namespace App\Services\PublicContent\Theming;

final class FallbackPublicContentDesignTokenSource implements PublicContentDesignTokenSource
{
    public function __construct(
        private readonly PublicContentDesignTokenSource $database,
        private readonly PublicContentDesignTokenSource $file,
    ) {
    }

    public function has(int $siteId): bool
    {
        return true;
    }

    public function defaults(int $siteId): array
    {
        return $this->resolve($siteId)->defaults($siteId);
    }

    public function overrides(int $siteId): array
    {
        return $this->resolve($siteId)->overrides($siteId);
    }

    private function resolve(int $siteId): PublicContentDesignTokenSource
    {
        return $this->database->has($siteId) ? $this->database : $this->file;
    }
}