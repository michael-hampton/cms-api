<?php

namespace App\Repositories\Shopping;

use App\Enums\Gifts\GiftQuantityRule;
use App\Enums\Gifts\GiftTriggerOperator;
use App\Enums\Gifts\GiftTriggerType;
use App\Enums\Gifts\GiftType;
use App\Framework\Support\Collection;
use App\Models\GiftPromotion;
use App\Models\GiftPromotionTrigger;
use App\Repositories\Repository;

class GiftPromotionRepository extends Repository
{
    /*
    |--------------------------------------------------------------------------
    | Read
    |--------------------------------------------------------------------------
    */

    public function findCandidatesForCart(
        array $productIds,
        array $subscriptionPlanIds,
        array $categoryIds,
        array $merchantIds,
        bool  $includeFirstTimeBuyer = false,
    ): Collection
    {

        $query = GiftPromotion::query()
            ->active()
            ->with(['triggers'])
            ->forMerchant($merchantIds)

            // Must have at least one trigger that could match
            ->whereHas('triggers', function ($q) use (
                $productIds,
                $subscriptionPlanIds,
                $categoryIds,
                $includeFirstTimeBuyer
            ) {

                $q->where(function ($inner) use ($productIds) {

                    $inner->where('type', GiftTriggerType::PRODUCT->value)
                        ->when(!empty($productIds), function ($q) use ($productIds) {
                            $q->whereIn('reference_id', $productIds);
                        });

                })->orWhere(function ($inner) use ($subscriptionPlanIds) {

                    $inner->where('type', GiftTriggerType::SUBSCRIPTION_PLAN->value)
                        ->when(!empty($subscriptionPlanIds), function ($q) use ($subscriptionPlanIds) {
                            $q->whereIn('reference_id', $subscriptionPlanIds);
                        });

                })->orWhere(function ($inner) use ($categoryIds) {

                    $inner->where('type', GiftTriggerType::CATEGORY->value)
                        ->when(!empty($categoryIds), function ($q) use ($categoryIds) {
                            $q->whereIn('reference_id', $categoryIds);
                        });

                })->orWhereIn('type', [
                    GiftTriggerType::CART_TOTAL->value,
                    GiftTriggerType::ITEM_COUNT->value,
                ]);

                if ($includeFirstTimeBuyer) {
                    $q->orWhere('type', GiftTriggerType::FIRST_TIME_BUYER->value);
                }
            });

        return $query->get()
            ->map(fn(GiftPromotion $promotion) => $this->hydratePromotion($promotion));
    }

    public function findTriggersForPromotions(array $promotionIds): Collection
    {
        if (empty($promotionIds)) {
            return collect();
        }

        return GiftPromotionTrigger::query()
            ->whereIn('promotion_id', $promotionIds)
            ->get()
            ->map(fn(GiftPromotionTrigger $trigger) => $this->hydrateTrigger($trigger))
            ->groupBy('promotion_id');
    }

    public function findById(int $id): ?object
    {
        $promotion = GiftPromotion::find($id);

        return $promotion
            ? $this->hydratePromotion($promotion)
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Write
    |--------------------------------------------------------------------------
    */

    public function createWithTriggers(array $promotionData, array $triggers): int
    {
        $this->assertValidGiftTarget($promotionData);

        foreach ($triggers as $index => $trigger) {
            $this->assertValidTrigger($trigger, $index);
        }

        return $this->database->transaction(function () use ($promotionData, $triggers) {

            $promotion = GiftPromotion::create(
                $this->serialisePromotion($promotionData)
            );

            foreach ($triggers as $trigger) {
                GiftPromotionTrigger::create(
                    $this->serialiseTrigger($trigger, $promotion)
                );
            }

            return $promotion->id;
        });
    }

    public function deactivate(int $promotionId): void
    {
        GiftPromotion::where('id', $promotionId)
            ->update([
                'active' => false,
                'updated_at' => now()
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Hydration
    |--------------------------------------------------------------------------
    */

    private function hydratePromotion(GiftPromotion $promotion): object
    {
        return (object)[
            'id' => $promotion->id,
            'merchantId' => $promotion->merchant_id,
            'giftType' => GiftType::from($promotion->gift_type),
            'giftProductId' => $promotion->gift_product_id,
            'giftSubscriptionPlanId' => $promotion->gift_subscription_plan_id,
            'quantityRule' => GiftQuantityRule::from($promotion->quantity_rule),
            'maxPerOrder' => $promotion->max_per_order,
            'exclusive' => $promotion->exclusive,
            'priority' => $promotion->priority,
            'startsAt' => $promotion->starts_at,
            'endsAt' => $promotion->ends_at,
            'active' => $promotion->active,
        ];
    }

    private function hydrateTrigger(GiftPromotionTrigger $trigger): object
    {
        return (object)[
            'id' => $trigger->id,
            'promotionId' => $trigger->promotion_id,
            'type' => GiftTriggerType::from($trigger->type),
            'operator' => GiftTriggerOperator::from($trigger->operator),
            'referenceId' => $trigger->reference_id,
            'value' => $trigger->value,
            'valueSet' => $trigger->value_set,
            'groupKey' => $trigger->group_key,
            'negated' => $trigger->negated,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Serialization + Validation
    |--------------------------------------------------------------------------
    */

    private function serialisePromotion(array $data): array
    {
        return [
            'merchant_id' => $data['merchant_id'] ?? null,
            'gift_type' => GiftType::from($data['gift_type'])->value,
            'gift_product_id' => $data['gift_product_id'] ?? null,
            'gift_subscription_plan_id' => $data['gift_subscription_plan_id'] ?? null,
            'quantity_rule' => GiftQuantityRule::from(
                $data['quantity_rule'] ?? 'one_per_qualifying'
            )->value,
            'max_per_order' => $data['max_per_order'] ?? 1,
            'exclusive' => $data['exclusive'] ?? false,
            'priority' => $data['priority'] ?? 0,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'active' => $data['active'] ?? true,
        ];
    }

    private function serialiseTrigger(array $data, GiftPromotion $giftPromotion): array
    {
        return [
            'type' => $data['type'],
            'operator' => $data['operator'],
            'reference_id' => $data['reference_id'] ?? null,
            'value' => $data['value'] ?? null,
            'value_set' => $data['value_set'] ?? null,
            'group_key' => $data['group_key'] ?? 'A',
            'negated' => $data['negated'] ?? false,
            'promotion_id' => $giftPromotion->id
        ];
    }

    private function assertValidGiftTarget(array $data): void
    {
        $hasProduct = !empty($data['gift_product_id']);
        $hasSubscription = !empty($data['gift_subscription_plan_id']);

        if ($hasProduct && $hasSubscription) {
            throw new \InvalidArgumentException(
                'Gift promotion cannot target both product and subscription'
            );
        }

        if (!$hasProduct && !$hasSubscription) {
            throw new \InvalidArgumentException(
                'Gift promotion must have a target'
            );
        }
    }

    private function assertValidTrigger(array $trigger, int $index): void
    {
        if (($trigger['type'] ?? null) === GiftTriggerType::FIRST_TIME_BUYER->value) {

            if (($trigger['operator'] ?? null) !== GiftTriggerOperator::EQUALS->value) {
                throw new \InvalidArgumentException(
                    "Trigger {$index}: FIRST_TIME_BUYER must use '=' operator"
                );
            }
        }
    }

    protected function getModelClass(): string
    {
        return GiftPromotion::class;
    }
}