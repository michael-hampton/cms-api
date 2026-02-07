<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Services\Subscriptions\SubscriptionPlanPricingService;

class SubscriptionPlanPricingController extends Controller
{
    public function __construct(
        private readonly SubscriptionPlanPricingService    $pricingService,
        private readonly SubscriptionPlanPricingRepository $pricingRepository
    )
    {
        parent::__construct();
    }

    public function index(Request $request, int $planId)
    {
        $filters = [
            'plan_id' => $planId,
            'status' => $request->input('status'),
            'search' => $request->input('search')
        ];

        $result = $this->pricingRepository->searchPricingTiersPaginated($filters);

        return $this->resourceResponse([
            'success' => true,
            'data' => $result['data']->toArray(),
            'pagination' => [
                'total' => $result['pagination']['total'],
                'page' => $result['pagination']['current_page'],
                'per_page' => $result['pagination']['per_page'],
                'total_pages' => $result['pagination']['total_pages'],
            ]
        ]);
    }

    public function store(Request $request, int $planId)
    {
        /**
         * [
         * 'duration_months' => 'required|integer|min:1',
         * 'issue_count' => 'required|integer|min:1',
         * 'price' => 'required|numeric|min:0',
         * 'original_price' => 'nullable|numeric|min:0',
         * 'digital_price' => 'nullable|numeric|min:0',
         * 'discount_percentage' => 'nullable|integer|min:0|max:100',
         * 'label' => 'nullable|string|max:100',
         * 'period_description' => 'nullable|string|max:255',
         * 'is_default' => 'nullable|boolean',
         * 'is_active' => 'nullable|boolean',
         * 'sort_order' => 'nullable|integer'
         * ]
         */

        $data = $request->all();

        $data['plan_id'] = $planId;

        try {
            $pricing = $this->pricingService->createPricingTier($data);

            $pricing = SubscriptionPlanPricing::find($pricing->id);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Pricing tier created successfully',
                'data' => $pricing->toArray()
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            Logger::error('Failed to create pricing tier', [
                'plan_id' => $planId,
                'error' => $e->getMessage()
            ]);

            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, int $planId, int $pricingId)
    {
        /**
         * [
         * 'duration_months' => 'sometimes|required|integer|min:1',
         * 'issue_count' => 'sometimes|required|integer|min:1',
         * 'price' => 'sometimes|required|numeric|min:0',
         * 'original_price' => 'nullable|numeric|min:0',
         * 'digital_price' => 'nullable|numeric|min:0',
         * 'discount_percentage' => 'nullable|integer|min:0|max:100',
         * 'label' => 'nullable|string|max:100',
         * 'period_description' => 'nullable|string|max:255',
         * 'is_default' => 'nullable|boolean',
         * 'is_active' => 'nullable|boolean',
         * 'sort_order' => 'nullable|integer'
         * ]
         */

        $data = $request->all();

        try {
            $pricing = $this->pricingService->updatePricingTier($pricingId, $data);

            $pricing = SubscriptionPlanPricing::find($pricingId);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Pricing tier updated successfully',
                'data' => $pricing->toArray()
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to update pricing tier', [
                'pricing_id' => $pricingId,
                'error' => $e->getMessage()
            ]);

            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(int $planId, int $pricingId)
    {
        try {
            $this->pricingService->deletePricingTier($pricingId);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Pricing tier deleted successfully'
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to delete pricing tier', [
                'pricing_id' => $pricingId,
                'error' => $e->getMessage()
            ]);

            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function setDefault(int $planId, int $pricingId)
    {
        try {
            $this->pricingService->setAsDefault($pricingId);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Default pricing tier updated'
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to set default pricing', [
                'pricing_id' => $pricingId,
                'error' => $e->getMessage()
            ]);

            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleActive(int $planId, int $pricingId)
    {
        try {
            $this->pricingService->toggleActive($pricingId);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Pricing tier status toggled'
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to toggle pricing status', [
                'pricing_id' => $pricingId,
                'error' => $e->getMessage()
            ]);

            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateSortOrder(Request $request, int $planId)
    {
        $data = $request->all();

        try {
            $this->pricingService->updateSortOrders($data['order']);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Sort order updated'
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to update sort order', [
                'plan_id' => $planId,
                'error' => $e->getMessage()
            ]);

            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}