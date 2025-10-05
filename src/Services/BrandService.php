<?php

namespace App\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Collection;
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

    public function createBrand(array $data, ?UploadedFile $logoFile = null): Brand
    {
        return $this->database->transaction(function() use ($data, $logoFile) {
            if ($logoFile && $logoFile->isValid()) {
                $data['logo'] = $this->imageUploadService->upload($logoFile);
            }

            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name'], [$this->brandRepository, 'findBySlug']);
            }

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

            if ($sourceBrand->logo) {
                $this->imageUploadService->delete($sourceBrand->logo);
            }

            $this->brandRepository->delete($sourceBrandId);

            return true;
        });
    }

    public function duplicateBrand(int $brandId, ?string $newName = null): Brand
    {
        return $this->database->transaction(function() use ($brandId, $newName) {
            $originalBrand = $this->brandRepository->find($brandId);

            if (!$originalBrand) {
                throw new \Exception("Brand not found");
            }

            $data = [
                'name' => $newName ?? ($originalBrand->name . ' (Copy)'),
                'description' => $originalBrand->description,
                'website' => $originalBrand->website,
                'status' => 'inactive',
            ];

            $data['slug'] = Str::slug($data['name'], [$this->brandRepository, 'findBySlug']);

            if ($originalBrand->logo) {
                try {
                    $data['logo'] = $this->imageUploadService->duplicate($originalBrand->logo);
                } catch (\Exception $e) {
                    $data['logo'] = null;
                }
            }

            return $this->brandRepository->create($data);
        });
    }
}