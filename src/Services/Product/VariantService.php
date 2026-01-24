<?php

namespace App\Services\Product;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Repositories\Product\VariantRepository;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;

class VariantService
{
    protected VariantRepository $repository;

    public function __construct(VariantRepository $repository)
    {
        $this->repository = $repository;
    }

    public function searchVariants(SearchCriteria $criteria): PaginatedResult
    {
        return $this->repository->search($criteria);
    }

    public function getVariant(int $id): ?Model
    {
        return $this->repository->find($id, ['product', 'images', 'merchants']);
    }

    public function updateVariant(int $id, array $data): ?Model
    {
        $variant = $this->repository->find($id);

        if (!$variant) {
            return null;
        }

        if (!empty($data['is_active'])) {
            $data['is_active'] = 1;
        }

        $images = $data['images'] ?? [];

        if (!empty($images) && is_string($images)) {
            $images = json_decode($images, true);
        }

        unset($data['images']);

        $result = $this->repository->update($id, $data);

        if (empty($result)) {
            return null;
        }

        if (!empty($images)) {
            $this->repository->syncVariantImages($variant->id, $variant->product_id, $images);
        }

        return $result ?? null;
    }

    public function deleteVariant(int $id): bool
    {
        $variant = $this->repository->find($id);

        if (!$variant) {
            return false;
        }

        // Delete variant images first
        $this->repository->deleteVariantImages($id);

        return $this->repository->delete($id);
    }

    public function updateVariantImages(int $id, array $images): bool
    {
        $variant = $this->repository->find($id);

        if (!$variant) {
            return false;
        }

        $this->repository->syncVariantImages($id, $variant->product_id, $images);

        return true;
    }

    public function toggleVariantStatus(int $id): ?array
    {
        $variant = $this->repository->find($id);

        if (!$variant) {
            return null;
        }

        $newStatus = !$variant->is_active;

        $this->repository->update($id, ['is_active' => $newStatus]);

        return [
            'is_active' => $newStatus
        ];
    }

    public function getVariantsByProduct(int $productId): Collection
    {
        return $this->repository->getByProduct($productId);
    }

    public function createVariant(array $data): Model
    {

        $images = $data['images'] ?? [];

        if (!empty($images) && is_string($images)) {
            $images = json_decode($images, true);
        }

        unset($data['images']);

        if (!empty($data['is_active'])) {
            $data['is_active'] = 1;
        }

        $variant = $this->repository->create($data);

        if (!empty($images)) {
            $this->repository->syncVariantImages($variant->id, $variant->product_id, $images);
        }

        return $this->repository->find($variant->id, ['product', 'images', 'merchants']);
    }
}