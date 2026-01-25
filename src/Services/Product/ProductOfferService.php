<?php

namespace App\Services\Product;

use App\Framework\Authorization\AuthenticationService;
use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\ProductOffer;
use App\Repositories\Product\ProductOfferRepository;
use Exception;

class ProductOfferService
{
    public function __construct(
        private readonly ProductOfferRepository $repository,
        private readonly AuthenticationService  $authenticationService
    )
    {
    }

    public function getOffer(int $id): ?Model
    {
        return $this->repository->find($id);
    }

    public function getActiveOffersForProduct(int $productId): Collection
    {
        return $this->repository->getActiveOffersForProduct($productId);
    }

    public function getActiveOffersForCategory(int $categoryId): Collection
    {
        return $this->repository->getActiveOffersForCategory($categoryId);
    }

    public function createOffer(array $data): Model
    {
        $this->validateOfferDates($data['start_date'], $data['end_date']);

        // Auto-fill status-related fields
        $data = $this->fillStatusFields($data);

        return $this->repository->create($data);
    }

    private function validateOfferDates(string $startDate, string $endDate): void
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

    public function updateOffer(int $id, array $data): ?ProductOffer
    {
        if (isset($data['start_date']) && isset($data['end_date'])) {
            $this->validateOfferDates($data['start_date'], $data['end_date']);
        }

        // Get current offer to check status changes
        $currentOffer = $this->repository->find($id);
        if ($currentOffer && isset($data['status'])) {
            $data = $this->fillStatusFieldsOnUpdate($data, $currentOffer);
        }

        return $this->repository->update($id, $data);
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

    private function fillStatusFieldsOnUpdate(array $data, ProductOffer $currentOffer): array
    {
        // Only fill if status is changing
        if ($data['status'] === $currentOffer->status) {
            return $data;
        }

        $userId = $this->authenticationService->getUserId();
        if (!$userId) {
            return $data;
        }

        if ($data['status'] === 'published' && !$currentOffer->published_at) {
            $data['published_by'] = $userId;
            $data['published_at'] = now_datetime();
        } elseif ($data['status'] === 'rejected' && !$currentOffer->rejected_at) {
            $data['rejected_by'] = $userId;
            $data['rejected_at'] = now_datetime();
        }

        return $data;
    }

    public function deleteOffer(int $id): bool
    {
        return $this->repository->delete($id);
    }

    public function hasActiveOffer(int $productId): bool
    {
        return $this->repository->hasActiveOffer($productId);
    }

    public function publish(int $id, int $userId): ?ProductOffer
    {
        return $this->repository->publish($id, $userId);
    }

    public function reject(int $id, int $userId, string $reason): ?ProductOffer
    {
        if (empty($reason)) {
            throw new Exception('Rejection reason is required');
        }

        return $this->repository->reject($id, $userId, $reason);
    }

    public function searchOffers(array $filters): Collection
    {
        return $this->repository->search($filters);
    }

    public function getByStatus(string $status): Collection
    {
        return $this->repository->getByStatus($status);
    }
}