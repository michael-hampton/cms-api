<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\DTO\Subscriptions\SubscriptionOfferFilters;
use App\Enums\Subscriptions\OfferType;
use App\Framework\Authorization\Auth;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Services\Subscriptions\SubscriptionOfferSearchService;

/**
 * GET /api/{site}/crm/subscription-offers
 *
 * Returns paginated virtual offer records derived from active pricing tiers.
 * Supports filters: search, site_id, plan_id, offer_type, has_discount,
 * has_intro_pricing, has_voucher, is_active.
 */
class CrmSubscriptionOfferController extends Controller
{
    public function __construct(
        private readonly SubscriptionOfferSearchService $offerSearchService,
    ) {
        parent::__construct();
    }

    public function index(Request $request): mixed
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $offerTypeRaw = $request->input('offer_type');
        $offerType    = null;

        if ($offerTypeRaw !== null && $offerTypeRaw !== '') {
            $offerType = OfferType::tryFrom($offerTypeRaw);

            if ($offerType === null) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Invalid offer_type. Accepted values: print, digital, intro, voucher.',
                ], 422);
            }
        }

        $filters = new SubscriptionOfferFilters(
            search:          $request->input('search') ?: null,
            siteId:          $request->input('site_id') ? (int) $request->input('site_id') : SiteContext::getId(),
            planId:          $request->input('plan_id') ? (int) $request->input('plan_id') : null,
            offerType:       $offerType,
            hasDiscount:     $this->nullableBool($request->input('has_discount')),
            hasIntroPricing: $this->nullableBool($request->input('has_intro_pricing')),
            hasVoucher:      $this->nullableBool($request->input('has_voucher')),
            isActive:        $this->nullableBool($request->input('is_active'), default: true),
            page:            max(1, (int) $request->input('page', 1)),
            perPage:         min(50, max(1, (int) $request->input('per_page', 15))),
            minPrice:        $request->input('min_price') ? (int) $request->input('min_price') : null,
            maxPrice:        $request->input('max_price') ? (int) $request->input('max_price') : null,
        );

        try {
            $result = $this->offerSearchService->search($filters);

            return $this->resourceResponse([
                'success' => true,
                'data'    => array_map(fn($offer) => $offer->toArray(), $result['items']),
                'pagination' => [
                    'total'        => $result['total'],
                    'per_page'     => $result['per_page'],
                    'current_page' => $result['page'],
                    'last_page'    => $result['last_page'],
                ],
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to search subscription offers', [
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse(['success' => false, 'message' => 'Failed to load offers.'], 500);
        }
    }

    /**
     * Convert a raw request input to a nullable bool.
     * Accepts: '1', 'true', 'yes' → true; '0', 'false', 'no' → false; null/'' → $default.
     */
    private function nullableBool(mixed $value, ?bool $default = null): ?bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (in_array($value, ['1', 'true', 'yes', true], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false', 'no', false], true)) {
            return false;
        }

        return $default;
    }
}