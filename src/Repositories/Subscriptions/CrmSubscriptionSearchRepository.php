<?php

namespace App\Repositories\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\Subscription;
use App\Repositories\Repository;

class CrmSubscriptionSearchRepository extends Repository
{
    /**
     * Apply advanced search filter parameters across relations without altering primary schemas.
     */
    public function searchSubscriptions(array $filters, int $page, int $perPage): array
    {
        $query = Subscription::query()
            ->join('subscription_plan_pricing', 'subscriptions.subscription_plan_pricing_id', '=', 'subscription_plan_pricing.id')
            ->join('subscription_plans', 'subscription_plan_pricing.plan_id', '=', 'subscription_plans.id')
            ->select('subscriptions.*');

        // ── Offer Type Structural Filtering ─────────────────────────────
        if (!empty($filters['offer_type'])) {
            switch ($filters['offer_type']) {
                case 'print':
                    $query->whereColumn('subscription_plan_pricing.sale_price', '<', 'subscription_plan_pricing.price')
                        ->where('subscription_plan_pricing.sale_price', '>', 0);
                    break;
                case 'digital':
                    $query->whereColumn('subscription_plan_pricing.digital_sale_price', '<', 'subscription_plan_pricing.digital_price')
                        ->where('subscription_plan_pricing.digital_sale_price', '>', 0);
                    break;
                case 'intro':
                    $query->whereNotNull('subscription_plan_pricing.intro_price')
                        ->where('subscription_plan_pricing.intro_cycles', '>', 0);
                    break;
                case 'voucher':
                    $query->whereExists(function ($sub) {
                        $sub->from('voucher_subscription_plan')
                            ->whereColumn('voucher_subscription_plan.subscription_plan_id', 'subscription_plan_pricing.plan_id');
                    });
                    break;
            }
        }

        // ── Has Discount Rule ───────────────────────────────────────────
        if (isset($filters['has_discount'])) {
            if ($filters['has_discount'] === true) {
                $query->where(function ($q) {
                    $q->whereColumn('subscription_plan_pricing.sale_price', '<', 'subscription_plan_pricing.price')
                        ->orWhereColumn('subscription_plan_pricing.digital_sale_price', '<', 'subscription_plan_pricing.digital_price');
                });
            } else {
                $query->where(function ($q) {
                    $q->where(function ($inner) {
                        $inner->whereNull('subscription_plan_pricing.sale_price')
                            ->orWhereColumn('subscription_plan_pricing.sale_price', '>=', 'subscription_plan_pricing.price');
                    })->where(function ($inner) {
                        $inner->whereNull('subscription_plan_pricing.digital_sale_price')
                            ->orWhereColumn('subscription_plan_pricing.digital_sale_price', '>=', 'subscription_plan_pricing.digital_price');
                    });
                });
            }
        }

        // ── Has Intro Pricing Rule ──────────────────────────────────────
        if (isset($filters['has_intro_pricing'])) {
            if ($filters['has_intro_pricing'] === true) {
                $query->whereNotNull('subscription_plan_pricing.intro_price')
                    ->where('subscription_plan_pricing.intro_cycles', '>', 0);
            } else {
                $query->where(function ($q) {
                    $q->whereNull('subscription_plan_pricing.intro_price')
                        ->orWhere('subscription_plan_pricing.intro_cycles', '<=', 0);
                });
            }
        }

        // ── Base Relations (Plan & Voucher ID mapping) ──────────────────
        if (!empty($filters['plan_id'])) {
            $query->where('subscription_plan_pricing.plan_id', (int) $filters['plan_id']);
        }

        if (!empty($filters['voucher_id'])) {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->from('voucher_subscription_plan')
                    ->whereColumn('voucher_subscription_plan.subscription_plan_id', 'subscription_plan_pricing.plan_id')
                    ->where('voucher_subscription_plan.voucher_id', (int) $filters['voucher_id']);
            });
        }

        // ── Target Evaluated Pricing Boundaries ─────────────────────────
        if (isset($filters['min_price'])) {
            $query->where('subscriptions.total_amount', '>=', (float) $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('subscriptions.total_amount', '<=', (float) $filters['max_price']);
        }

        $total = (clone $query)->count();
        $offset = ($page - 1) * $perPage;
        $items = $query->limit($perPage)->offset($offset)->get();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    protected function getModelClass(): string
    {
        return Subscription::class;
    }
}