<?php

namespace App\Services\Offers;

use App\Enums\OfferStatus;
use App\Models\ProductOffer;

class OfferStatusTransitionHandler
{
    public function fillStatusFields(array $data, int $userId): array
    {
        if (!isset($data['status'])) {
            return $data;
        }

        $status = OfferStatus::from($data['status']);

        return match ($status) {
            OfferStatus::PUBLISHED => array_merge($data, [
                'published_by' => $userId,
                'published_at' => now_datetime()
            ]),
            OfferStatus::REJECTED => array_merge($data, [
                'rejected_by' => $userId,
                'rejected_at' => now_datetime()
            ]),
            default => $data
        };
    }

    public function fillStatusFieldsOnUpdate(
        array        $data,
        ProductOffer $currentOffer,
        int          $userId
    ): array
    {
        if (!isset($data['status']) || $data['status'] === $currentOffer->status) {
            return $data;
        }

        $newStatus = OfferStatus::from($data['status']);

        if ($newStatus === OfferStatus::PUBLISHED && !$currentOffer->published_at) {
            $data['published_by'] = $userId;
            $data['published_at'] = now_datetime();
        } elseif ($newStatus === OfferStatus::REJECTED && !$currentOffer->rejected_at) {
            $data['rejected_by'] = $userId;
            $data['rejected_at'] = now_datetime();
        }

        return $data;
    }
}