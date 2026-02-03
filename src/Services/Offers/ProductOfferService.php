<?php

namespace App\Services\Offers;

use App\Enums\OfferAction;
use App\Enums\OfferStatus;
use App\Framework\Authorization\AuthenticationService;
use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\ProductOffer;
use App\Repositories\Offers\ProductOfferRepository;
use Exception;

class ProductOfferService
{
    public function __construct(
        private readonly ProductOfferRepository $repository,
        private readonly AuthenticationService        $authenticationService,
        private readonly OfferStatusTransitionHandler $statusHandler
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

        $userId = $this->authenticationService->getUserId();
        if ($userId && isset($data['status'])) {
            $data = $this->statusHandler->fillStatusFields($data, $userId);
        }

        return $this->repository->create($data);
    }

    private function validateOfferDates(string $startDate, string $endDate): void
    {
        $start = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $startDate);
        $end = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $endDate);

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

        $currentOffer = $this->repository->find($id);
        if ($currentOffer && isset($data['status'])) {
            $userId = $this->authenticationService->getUserId();
            if ($userId) {
                $data = $this->statusHandler->fillStatusFieldsOnUpdate($data, $currentOffer, $userId);
            }
        }

        return $this->repository->update($id, $data);
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
        OfferStatus::from($status); // Validate enum
        return $this->repository->getByStatus($status);
    }

    public function trackClick(
        int     $offerId,
        ?int    $memberId,
        string  $action,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): Model
    {
        OfferAction::from($action); // Validate enum

        return $this->repository->trackClick($offerId, $memberId, $action, $ipAddress, $userAgent);
    }

    /**
     * Get offers with comprehensive search and filtering
     */
    public function getOffersForWeb(array $filters): array
    {
        $offerData = $this->repository->searchOffersWithFilters($filters);

        return [
            'items' => $offerData['data']->toArray(),
            'pagination' => [
                'current_page' => (int)$offerData['page'],
                'per_page' => (int)$offerData['per_page'],
                'total' => $offerData['total'],
                'total_pages' => ceil($offerData['total'] / $offerData['per_page']),
                'from' => (($offerData['page'] - 1) * $offerData['per_page']) + 1,
                'to' => min($offerData['page'] * $offerData['per_page'], $offerData['total'])
            ]
        ];
    }

    public function getActiveOffers(int $limit = 10): array
    {
        $offers = $this->repository->all()
            ->filter(fn($offer) => $offer->isCurrentlyActive())
            ->take($limit);

        return $offers->map(function ($offer) {
            $product = $offer->product;

            return [
                'offer_id' => $offer->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_slug' => $product->slug,
                'product_image' => $product->main_image_url,
                'original_price' => $product->price,
                'offer_price' => $offer->sale_price,
                'discount_percentage' => $offer->discount_percentage,
                'start_date' => $offer->start_date,
                'end_date' => $offer->end_date,
                'in_stock' => $product->in_stock ?? true,
                'merchant_name' => $offer->merchant?->name,
            ];
        })->toArray();
    }

}