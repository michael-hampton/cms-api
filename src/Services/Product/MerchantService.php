<?php

namespace App\Services\Product;

use App\Framework\Http\UploadedFile;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\Model;
use App\Repositories\Product\MerchantRepository;
use App\Services\Cms\ImageUploadService;
use Exception;

class MerchantService
{
    protected MerchantRepository $repository;
    protected ImageUploadService $imageUploadService;

    public function __construct(
        MerchantRepository $repository,
        ImageUploadService $imageUploadService
    )
    {
        $this->repository = $repository;
        $this->imageUploadService = $imageUploadService;

        $this->imageUploadService
            ->setAllowedMimeTypes([
                'image/jpeg',
                'image/jpg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/svg+xml'
            ])
            ->setMaxFileSize(5 * 1024 * 1024); // 5MB
    }

    public function getAllMerchants(): Collection
    {
        return $this->repository->all();
    }

    public function getActiveMerchants(): Collection
    {
        return $this->repository->getActive();
    }

    public function getMerchant(int $id): ?Model
    {
        return $this->repository->find($id, ['contact', 'urls', 'sites', 'productFeeds']);
    }

    public function createMerchant(array $data, ?UploadedFile $logoFile = null): Model
    {
        if ($logoFile && $logoFile->isValid()) {
            try {
                $data['logo'] = $this->imageUploadService->uploadToPath(
                    $logoFile,
                    'merchants/' . date('Y-m')
                );
            } catch (Exception $e) {
                throw new Exception('Failed to upload merchant logo: ' . $e->getMessage());
            }
        }

        $urls = $data['urls'] ?? [];
        $siteIds = $data['site_ids'] ?? [];

        unset($data['urls'], $data['site_ids']);

        $merchant = $this->repository->create($data);

        if (!empty($urls)) {
            $this->repository->syncUrls($merchant->id, $urls);
        }

        if (!empty($siteIds)) {
            $this->repository->syncSites($merchant->id, $siteIds);
        }

        return $merchant;
    }

    public function updateMerchant(int $id, array $data, ?UploadedFile $logoFile = null): ?Model
    {
        $merchant = $this->repository->find($id);

        if (!$merchant) {
            return null;
        }

        $oldLogoPath = $merchant->logo ?? null;

        if ($logoFile && $logoFile->isValid()) {
            try {
                $data['logo'] = $this->imageUploadService->uploadToPath(
                    $logoFile,
                    'merchants/' . date('Y-m'),
                    $oldLogoPath
                );
            } catch (Exception $e) {
                throw new Exception('Failed to upload merchant logo: ' . $e->getMessage());
            }
        }

        $urls = $data['urls'] ?? null;
        $siteIds = $data['site_ids'] ?? null;

        unset($data['urls'], $data['site_ids']);

        $merchant = $this->repository->update($id, $data);

        if ($urls !== null) {
            $this->repository->syncUrls($merchant->id, $urls);
        }

        if ($siteIds !== null) {
            $this->repository->syncSites($merchant->id, $siteIds);
        }

        return $merchant;
    }

    public function deleteMerchant(int $id): bool
    {
        $merchant = $this->repository->find($id);

        if (!$merchant) {
            return false;
        }

        if ($merchant->logo) {
            $this->deleteLogo($merchant->logo);
        }

        $this->repository->deleteUrls($id);

        return $this->repository->delete($id);
    }

    protected function deleteLogo(string $path): void
    {
        try {
            $this->imageUploadService->delete($path);
        } catch (Exception $e) {
            Logger::error('Failed to delete merchant logo: ' . $e->getMessage());
        }
    }

    public function toggleStatus(int $id): ?Model
    {
        $merchant = $this->repository->find($id);

        if (!$merchant) {
            return null;
        }

        return $this->repository->update($id, [
            'is_active' => !$merchant->is_active
        ]);
    }

    public function bulkUpdateStatus(array $ids, bool $isActive): int
    {
        return $this->repository->bulkUpdateStatus($ids, $isActive);
    }

    public function bulkDelete(array $ids): int
    {
        foreach ($ids as $id) {
            $merchant = $this->repository->find($id);
            if ($merchant && $merchant->logo) {
                $this->deleteLogo($merchant->logo);
            }
        }

        return $this->repository->bulkDelete($ids);
    }

    public function getMerchantsWithProductCount(): Collection
    {
        return $this->repository->getMerchantsWithProductCount();
    }

    public function getMerchantsBySite(int $siteId): Collection
    {
        return $this->repository->findBySite($siteId);
    }
}