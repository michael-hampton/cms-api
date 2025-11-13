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
        private BrandRepository $brandRepository,
        private ImageUploadService $imageUploadService,
        ?Database $database = null
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

    public function mergeBrands(int $sourceBrandId, int $targetBrandId): bool
    {
        if ($sourceBrandId === $targetBrandId) {
            throw new \Exception("Cannot merge a brand with itself");
        }

        return $this->database->transaction(function() use ($sourceBrandId, $targetBrandId) {
            $sourceBrand = $this->brandRepository->find($sourceBrandId);
            $targetBrand = $this->brandRepository->find($targetBrandId);

            if (!$sourceBrand || !$targetBrand) {
                throw new \Exception("One or both brands not found");
            }

            $products = $this->brandRepository->getProductsByBrandId($sourceBrandId);

            foreach ($products as $product) {
                $product->brand_id = $targetBrandId;
                $product->save();
            }

            // Add merge history
            $targetBrand->addCloneRecord('merged_from', $sourceBrand->id, null);
            $sourceBrand->addCloneRecord('merged_to', $targetBrand->id, null);

            if ($sourceBrand->logo) {
                $this->imageUploadService->delete($sourceBrand->logo);
            }

            $this->brandRepository->delete($sourceBrandId);

            return true;
        });
    }

    public function duplicateBrand(int $brandId, ?string $newName = null, ?int $siteId = null): Brand
    {
        return $this->database->transaction(function() use ($brandId, $newName, $siteId) {
            $originalBrand = $this->brandRepository->find($brandId);

            if (!$originalBrand) {
                throw new \Exception("Brand not found");
            }

            $targetSiteId = $siteId ?? SiteContext::getId();

            $data = [
                'name' => $newName ?? ($originalBrand->name . ' (Copy)'),
                'description' => $originalBrand->description,
                'website' => $originalBrand->website,
                'status' => 'inactive',
                'seo_title' => $originalBrand->seo_title,
                'seo_description' => $originalBrand->seo_description,
                'no_index' => $originalBrand->no_index ?? false,
                'site_id' => $siteId ?? SiteContext::getId(),
                'canonical_url' => null, // Don't copy canonical URL
            ];

            $data['slug'] = Str::slug($data['name'], [$this->brandRepository, 'findBySlug']);

            if ($originalBrand->logo) {
                try {
                    $data['logo'] = $this->imageUploadService->duplicate($originalBrand->logo);
                } catch (\Exception $e) {
                    $data['logo'] = null;
                }
            }

            $newBrand = $this->brandRepository->create($data);

            // Add clone history with site information
            if ($targetSiteId !== $originalBrand->site_id) {
                $originalBrand->addCloneRecord('cloned_to', $newBrand->id, $targetSiteId);
                $newBrand->addCloneRecord('cloned_from', $originalBrand->id, $originalBrand->site_id);
            } else {
                $originalBrand->addCloneRecord('cloned_to', $newBrand->id, null);
                $newBrand->addCloneRecord('cloned_from', $originalBrand->id, null);
            }

            return $newBrand;
        });
    }

    public function bulkDelete(array $brandIds): array
    {
        return $this->database->transaction(function() use ($brandIds) {
            $deleted = [];
            $failed = [];

            foreach ($brandIds as $brandId) {
                try {
                    $brand = $this->brandRepository->find($brandId);

                    if (!$brand) {
                        $failed[] = ['id' => $brandId, 'reason' => 'Brand not found'];
                        continue;
                    }

                    $productsCount = $this->brandRepository->getProductsByBrandId($brandId)->count();

                    if ($productsCount > 0) {
                        $failed[] = [
                            'id' => $brandId,
                            'reason' => "Brand has {$productsCount} associated products"
                        ];
                        continue;
                    }

                    if ($brand->logo) {
                        $this->imageUploadService->delete($brand->logo);
                    }

                    if ($brand->delete()) {
                        $deleted[] = $brandId;
                    } else {
                        $failed[] = ['id' => $brandId, 'reason' => 'Delete failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $brandId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'deleted' => $deleted,
                'failed' => $failed,
                'total' => count($brandIds)
            ];
        });
    }
}