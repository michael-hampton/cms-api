<?php

namespace App\Repositories\Offers;

use App\Framework\Support\Collection;
use App\Models\TieredPromotion;
use App\Repositories\Repository;

class TieredPromotionRepository extends Repository
{
    public function findApplicablePromotion(
        int  $subtotalCents,
        bool $isSubscription
    ): ?TieredPromotion
    {
        return $this->getActivePromotions()
            ->filter(fn($promo) => $promo->appliesTo($isSubscription))
            ->filter(fn($promo) => $subtotalCents >= $promo->min_subtotal_cents)
            ->first();
    }

    public function getActivePromotions(): Collection
    {
        return TieredPromotion::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->orderBy('min_subtotal_cents', 'desc')
            ->get();
    }

    protected function getModelClass(): string
    {
        return TieredPromotion::class;
    }
}