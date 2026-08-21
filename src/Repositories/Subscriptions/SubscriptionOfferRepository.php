<?php

namespace App\Repositories\Subscriptions;

use App\DTO\Subscriptions\SubscriptionOfferFilters;
use App\Framework\Support\Collection;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Repository;

/**
 * Responsible only for fetching raw pricing/plan/voucher data needed to derive
 * CRM offers.  No offer-derivation logic lives here — that belongs in
 * SubscriptionOfferSearchService.
 */
class SubscriptionOfferRepository extends Repository
{
    /**
     * Fetch active pricing tiers matching the given filters, with their parent
     * plan and linked vouchers eager-loaded.
     *
     * Each returned tier row contains enough data for the service to derive all
     * offer types (print, digital, intro, voucher) without additional queries.
     *
     * @return array{items: Collection, total: int}
     */
    public function findPricingTiersForOffers(SubscriptionOfferFilters $filters): array
    {
        $query = SubscriptionPlanPricing::with(['plan', 'plan.promotion'])
            ->join('subscription_plans', 'subscription_plans.id', '=', 'subscription_plan_pricing.plan_id')
            ->select('subscription_plan_pricing.*');

        // ── Active plans only ──────────────────────────────────────────────
        $query->where('subscription_plans.is_active', true);

        // ── Active tiers only (default; can be overridden) ─────────────────
        if ($filters->isActive !== null) {
            $query->where('subscription_plan_pricing.is_active', $filters->isActive);
        }

        // ── Site filter ────────────────────────────────────────────────────
        if ($filters->siteId !== null) {
            $query->where('subscription_plans.site_id', $filters->siteId);
        }

        // ── Plan filter ────────────────────────────────────────────────────
        if ($filters->planId !== null) {
            $query->where('subscription_plan_pricing.plan_id', $filters->planId);
        }

        // ── Search: plan name or pricing label ─────────────────────────────
        if ($filters->search !== null && $filters->search !== '') {
            $term = '%' . $filters->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('subscription_plans.name', 'LIKE', $term)
                    ->orWhere('subscription_plan_pricing.label', 'LIKE', $term);
            });
        }

        // ── Discount filter: has any sale pricing ──────────────────────────
        if ($filters->hasDiscount === true) {
            $query->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNotNull('subscription_plan_pricing.sale_price')
                        ->whereColumn('subscription_plan_pricing.sale_price', '<', 'subscription_plan_pricing.price');
                })->orWhere(function ($inner) {
                    $inner->whereNotNull('subscription_plan_pricing.digital_sale_price')
                        ->whereColumn('subscription_plan_pricing.digital_sale_price', '<', 'subscription_plan_pricing.digital_price');
                });
            });
        } elseif ($filters->hasDiscount === false) {
            $query->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNull('subscription_plan_pricing.sale_price')
                        ->orWhere('subscription_plan_pricing.sale_price', '<=', 0)
                        ->orWhereColumn('subscription_plan_pricing.sale_price', '>=', 'subscription_plan_pricing.price');
                })->where(function ($inner) {
                    $inner->whereNull('subscription_plan_pricing.digital_price')
                        ->orWhere('subscription_plan_pricing.digital_price', '<=', 0)
                        ->orWhereNull('subscription_plan_pricing.digital_sale_price')
                        ->orWhere('subscription_plan_pricing.digital_sale_price', '<=', 0)
                        ->orWhereColumn('subscription_plan_pricing.digital_sale_price', '>=', 'subscription_plan_pricing.digital_price');
                });
            });
        }

        // ── Intro pricing filter ───────────────────────────────────────────
        if ($filters->hasIntroPricing === true) {
            $query->whereNotNull('subscription_plan_pricing.intro_price')
                ->where('subscription_plan_pricing.intro_cycles', '>', 0);
        } elseif ($filters->hasIntroPricing === false) {
            $query->where(function ($q) {
                $q->whereNull('subscription_plan_pricing.intro_price')
                    ->orWhere('subscription_plan_pricing.intro_cycles', '<=', 0);
            });
        }

        // ── Voucher filter: plan has at least one linked voucher ───────────
        if ($filters->hasVoucher === true) {
            $query->whereRaw(
                'EXISTS (
            SELECT 1
            FROM voucher_subscription_plan
            WHERE voucher_subscription_plan.subscription_plan_id = subscription_plan_pricing.plan_id
        )'
            );
        } elseif ($filters->hasVoucher === false) {
            $query->whereRaw(
                'NOT EXISTS (
            SELECT 1
            FROM voucher_subscription_plan
            WHERE voucher_subscription_plan.subscription_plan_id = subscription_plan_pricing.plan_id
        )'
            );
        }

        // ── created_at / updated_at range filters ───────────────────────────
        if ($filters->createdFrom !== null) {
            $query->where('subscription_plan_pricing.created_at', '>=', $filters->createdFrom . ' 00:00:00');
        }

        if ($filters->createdTo !== null) {
            $query->where('subscription_plan_pricing.created_at', '<=', $filters->createdTo . ' 23:59:59');
        }

        if ($filters->updatedFrom !== null) {
            $query->where('subscription_plan_pricing.updated_at', '>=', $filters->updatedFrom . ' 00:00:00');
        }

        if ($filters->updatedTo !== null) {
            $query->where('subscription_plan_pricing.updated_at', '<=', $filters->updatedTo . ' 23:59:59');
        }

        $total = (clone $query)->count();

        $offset = ($filters->page - 1) * $filters->perPage;

        $items = $query
            ->orderBy('subscription_plans.sort_order')
            ->orderBy('subscription_plan_pricing.sort_order')
            ->limit($filters->perPage)
            ->offset($offset)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    protected function getModelClass(): string
    {
        return SubscriptionPlanPricing::class;
    }
}