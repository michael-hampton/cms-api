<?php

namespace App\Services\Cms;

use App\Models\Site;
use App\Repositories\Cms\SiteRepository;

class SiteService
{
    private SiteRepository $repository;

    public function __construct(SiteRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getAllSites(): array
    {
        $sites = Site::all();
        return $sites->toArray();
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
        $site = $this->repository->update($id, ['is_active' => $isActive]);
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
}