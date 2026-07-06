<?php
namespace App\Services\PublicContent\Theming;

use App\Repositories\Cms\SiteRepository;

final class FilePublicContentDesignTokenSource implements PublicContentDesignTokenSource
{
    private const string CONFIG_ROOT = 'public-content-design-tokens';

    public function __construct(
        private readonly SiteRepository $siteRepository,
    ) {
    }

    public function has(int $siteId): bool
    {
        return true;
    }

    public function defaults(int $siteId): array
    {
        return (array) config(self::CONFIG_ROOT . '.defaults', []);
    }

    public function overrides(int $siteId): array
    {
        $site = $this->siteRepository->find($siteId);

        if ($site === null) {
            return [];
        }

        return (array) config(self::CONFIG_ROOT . '.sites.' . $site->slug, []);
    }
}