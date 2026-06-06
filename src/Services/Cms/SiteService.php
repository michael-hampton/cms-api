<?php

namespace App\Services\Cms;

use App\Repositories\Cms\SiteRepository;
use App\Repositories\OpenCollab\UserSiteRepository;
use App\Services\OpenCollab\PermissionCacheInvalidator;

class SiteService
{
    private SiteRepository $repository;

    public function __construct(SiteRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getAllSites(): array
    {
        return $this->repository->findAll();
    }

    public function getSiteById(int $id): ?array
    {
        $site = $this->repository->find($id);
        return $site ? $site->toArray() : null;
    }

    public function getSiteByDomain(string $domain): ?array
    {
        $site = $this->repository->findByDomain($domain);
        return $site ? $site->toArray() : null;
    }

    public function createSite(array $data): array
    {
        $site = $this->repository->create($data);
        return $site->toArray();
    }

    public function updateSite(int $id, array $data): array
    {
        $site = $this->repository->update($id, $data);
        return $site->toArray();
    }

    public function updateContactInfo(int $id, array $contactData): array
    {
        $site = $this->repository->updateContactInfo($id, $contactData);
        return $site->toArray();
    }

    public function updateSocialMedia(int $id, array $socialData): array
    {
        $site = $this->repository->update($id, $socialData);
        return $site->toArray();
    }

    public function updateLogo(int $id, string $logoPath): array
    {
        $site = $this->repository->update($id, ['logo' => $logoPath]);
        return $site->toArray();
    }

    public function updateFavicon(int $id, string $faviconPath): array
    {
        $site = $this->repository->update($id, ['favicon' => $faviconPath]);
        return $site->toArray();
    }

    public function updateSettings(int $id, array $settings): array
    {
        $site = $this->repository->find($id);

        if (!$site) {
            throw new \Exception("Site not found");
        }

        $currentSettings = $site->settings ?? [];
        $mergedSettings = array_merge($currentSettings, $settings);

        $site = $this->repository->update($id, ['settings' => $mergedSettings]);
        return $site->toArray();
    }

    public function toggleStatus(int $id, bool $isActive): array
    {
        $existing = $this->repository->find($id);
        $site = $this->repository->update($id, ['is_active' => $isActive]);

        if ($existing && (bool) $existing->is_active && !$isActive) {
            $this->invalidateArchivedSitePermissions($id);
        }

        return $site->toArray();
    }

    public function deleteSite(int $id): bool
    {
        $site = $this->repository->find($id);

        if (!$site) {
            throw new \Exception("Site not found");
        }

        if ($site->isDefault()) {
            throw new \Exception("Cannot delete default site");
        }

        return $this->repository->delete($id);
    }

    private function invalidateArchivedSitePermissions(int $siteId): void
    {
        try {
            $userIds = app(UserSiteRepository::class)->userIdsForSite($siteId);
            app(PermissionCacheInvalidator::class)->invalidateUsers($userIds, $siteId);
        } catch (\Throwable $exception) {
            \App\Framework\Support\Logger::warning('Permission cache site archive fan-out failure', [
                'operation' => 'site_archive_fanout',
                'site_id' => $siteId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
