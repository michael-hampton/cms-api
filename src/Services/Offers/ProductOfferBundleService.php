<?php

namespace App\Services\Offers;

use App\Framework\Authorization\AuthenticationService;
use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\ProductOfferBundle;
use App\Repositories\Offers\ProductOfferBundleRepository;
use Exception;

class ProductOfferBundleService
{
    public function __construct(
        private readonly ProductOfferBundleRepository $repository,
        private readonly AuthenticationService        $authenticationService
    )
    {
    }

    public function getBundle(int $id): ?Model
    {
        return $this->repository->find($id);
    }

    public function getActiveBundles(): Collection
    {
        return $this->repository->getActiveBundles();
    }

    public function createBundle(array $data): Model
    {
        $this->validateBundleDates($data['start_date'], $data['end_date']);
        $this->validateBundleItems($data['items'] ?? []);
        $this->calculateBundlePricing($data);

        $data = $this->fillStatusFields($data);

        return $this->repository->create($data);
    }

    private function validateBundleDates(string $startDate, string $endDate): void
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);

        if ($start === false || $end === false) {
            throw new Exception('Invalid date format');
        }

        if ($end <= $start) {
            throw new Exception('End date must be after start date');
        }
    }

    private function validateBundleItems(array $items): void
    {
        if (empty($items)) {
            throw new Exception('Bundle must contain at least one item');
        }

        if (count($items) < 2) {
            throw new Exception('Bundle must contain at least two items');
        }
    }

    private function calculateBundlePricing(array &$data): void
    {
        // This would typically fetch the offers and calculate total price
        // For now, we'll assume total_price is provided
        if (!isset($data['total_price']) || !isset($data['bundle_price'])) {
            return;
        }

        $savings = $data['total_price'] - $data['bundle_price'];
        $data['discount_percentage'] = $data['total_price'] > 0
            ? (int)round(($savings / $data['total_price']) * 100)
            : 0;
    }

    private function fillStatusFields(array $data): array
    {
        if (!isset($data['status'])) {
            return $data;
        }

        $userId = $this->authenticationService->getUserId();
        if (!$userId) {
            return $data;
        }

        if ($data['status'] === 'published') {
            $data['published_by'] = $userId;
            $data['published_at'] = now_datetime();
        } elseif ($data['status'] === 'rejected') {
            $data['rejected_by'] = $userId;
            $data['rejected_at'] = now_datetime();
        }

        return $data;
    }

    public function updateBundle(int $id, array $data): ?ProductOfferBundle
    {
        if (isset($data['start_date']) && isset($data['end_date'])) {
            $this->validateBundleDates($data['start_date'], $data['end_date']);
        }

        if (isset($data['items'])) {
            $this->validateBundleItems($data['items']);
        }

        if (isset($data['items']) || isset($data['bundle_price'])) {
            $this->calculateBundlePricing($data);
        }

        $currentBundle = $this->repository->find($id);
        if ($currentBundle && isset($data['status'])) {
            $data = $this->fillStatusFieldsOnUpdate($data, $currentBundle);
        }

        return $this->repository->update($id, $data);
    }

    private function fillStatusFieldsOnUpdate(array $data, ProductOfferBundle $currentBundle): array
    {
        if ($data['status'] === $currentBundle->status) {
            return $data;
        }

        $userId = $this->authenticationService->getUserId();
        if (!$userId) {
            return $data;
        }

        if ($data['status'] === 'published' && !$currentBundle->published_at) {
            $data['published_by'] = $userId;
            $data['published_at'] = now_datetime();
        } elseif ($data['status'] === 'rejected' && !$currentBundle->rejected_at) {
            $data['rejected_by'] = $userId;
            $data['rejected_at'] = now_datetime();
        }

        return $data;
    }

    public function deleteBundle(int $id): bool
    {
        return $this->repository->delete($id);
    }

    public function publish(int $id, int $userId): ?ProductOfferBundle
    {
        return $this->repository->publish($id, $userId);
    }

    public function reject(int $id, int $userId, string $reason): ?ProductOfferBundle
    {
        if (empty($reason)) {
            throw new Exception('Rejection reason is required');
        }

        return $this->repository->reject($id, $userId, $reason);
    }

    public function getByStatus(string $status): Collection
    {
        return $this->repository->getByStatus($status);
    }
}