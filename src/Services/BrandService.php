<?php

namespace App\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Framework\Support\Str;
use App\Models\Brand;
use App\Repositories\BrandRepository;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;

class BrandService
{
    private Database $database;

    public function __construct(
        private readonly BrandRepository    $brandRepository,
        private readonly ImageUploadService $imageUploadService,
        ?Database                           $database = null
    ) {
        $this->database = $database ?? Database::getInstance();
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        return $this->brandRepository->search($criteria);
    }

    public function getAllBrands(): Collection
    {
        return Brand::orderBy('name', 'asc')->get();
    }

    public function getActiveBrands(): Collection
    {
        return $this->brandRepository->getActiveBrands();
    }

    public function getBrandById(int $id): ?Brand
    {
        return Brand::with(['products'])->find($id);
    }

    public function getBrandBySlug(string $slug): ?Brand
    {
        $brand = $this->brandRepository->findBySlug($slug);
        if ($brand) {
            $brand->load(['products']);
        }
        return $brand;
    }

    public function createBrand(array $data, int $siteId, ?UploadedFile $logoFile = null): Brand
    {
        return $this->database->transaction(function() use ($data, $logoFile, $siteId) {
            if ($logoFile && $logoFile->isValid()) {
                $data['logo'] = $this->imageUploadService->upload($logoFile);
            }

            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name'], [$this->brandRepository, 'findBySlug']);
            }

            $data['site_id'] = $siteId;

            return $this->brandRepository->create($data);
        });
    }

    public function updateBrand(int $id, array $data, ?UploadedFile $logoFile = null): Brand
    {
        return $this->database->transaction(function() use ($id, $data, $logoFile) {
            $brand = $this->brandRepository->find($id);

            if (!$brand) {
                throw new \Exception("Brand not found");
            }

            if ($logoFile && $logoFile->isValid()) {
                $oldLogo = $brand->logo;
                $data['logo'] = $this->imageUploadService->upload($logoFile, $oldLogo);
            }

            if (!empty($data['name']) && $data['name'] !== $brand->name && empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name'], [$this->brandRepository, 'findBySlug']);
            }

            $updatedBrand = $this->brandRepository->update($id, $data);

            if (!$updatedBrand) {
                throw new \Exception("Failed to update brand");
            }

            return $updatedBrand;
        });
    }

    public function delete(int $brandId, ?int $reassignToBrandId = null): bool
    {
        $brand = $this->brandRepository->find($brandId);

        if (!$brand) {
            throw new \Exception('Brand not found');
        }

        $productsCount = $this->brandRepository->getProductsByBrandId($brandId)->count();

        if ($productsCount > 0) {
            if ($reassignToBrandId === null) {
                throw new CannotDeleteException('brand', $productsCount);
            }

            if ($reassignToBrandId === $brandId) {
                throw new \InvalidArgumentException('Cannot reassign to the same brand being deleted');
            }

            $reassignBrand = $this->brandRepository->find($reassignToBrandId);

            if (!$reassignBrand) {
                throw new \Exception('Reassignment brand not found');
            }

            $this->database->transaction(function () use ($brand, $reassignToBrandId) {
                $brand->products()->update(['brand_id' => $reassignToBrandId]);

                if ($brand->logo) {
                    $this->imageUploadService->delete($brand->logo);
                }

                $brand->delete();
            });

            return true;
        }

        if ($brand->logo) {
            $this->imageUploadService->delete($brand->logo);
        }

        return $brand->delete();
    }

    public function checkDeletable(int $brandId): array
    {
        $brand = $this->brandRepository->find($brandId);

        if (!$brand) {
            throw new \Exception('Brand not found');
        }

        $productsCount = $brand->products()->count();

        return [
            'can_delete' => $productsCount === 0,
            'products_count' => $productsCount,
            'requires_reassignment' => $productsCount > 0
        ];
    }

    public function getAlternativeBrands(int $brandId): Collection
    {
        return $this->brandRepository->getAlternatives($brandId);
    }
}