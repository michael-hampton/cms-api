<?php

namespace App\Controllers\Shopping;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Shopping\CartService;

final class SubscriptionCartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
    ) {
        parent::__construct();
    }

    public function add(Request $request): mixed
    {
        $planId = (int) $request->input('plan_id');
        $requestData = $request->all();

        if ($planId <= 0) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Plan ID required',
            ], 400);
        }

        $plan = $this->subscriptionPlanRepository->find($planId);

        if (!$plan || !$plan->is_active) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Subscription plan not found or inactive',
            ], 400);
        }

        $deliveryType = $this->resolveDeliveryType($requestData, $plan);

        if ($deliveryType === null) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Unable to resolve delivery type for this subscription plan',
            ], 400);
        }

        $data = [
            'pricing_tier_id' => $requestData['pricing_id'] ?? $requestData['pricing_tier_id'] ?? null,
            'duration_months' => $requestData['duration_months'] ?? null,
            'issue_count' => $requestData['issues'] ?? null,
            'voucher_code' => $requestData['voucher_code'] ?? null,
            'delivery_type' => $deliveryType,
        ];

        $result = $this->cartService->addSubscriptionToCart($planId, $deliveryType, $data);

        return $this->resourceResponse(array_merge($result, [
            'count' => $this->cartService->getCount(),
            'total' => $this->cartService->getTotal(),
            'items' => $this->cartService->getItems(),
        ]));
    }

    private function resolveDeliveryType(array $requestData, SubscriptionPlan $plan): ?string
    {
        $requested = trim((string) ($requestData['delivery_type'] ?? ''));

        if ($requested !== '') {
            return $requested;
        }

        $options = $plan->getDeliveryOptions();

        if ($options !== []) {
            return (string) $options[0];
        }

        return $plan->getDeliveryType()?->value;
    }
}
