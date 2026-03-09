<?php

namespace App\Controllers\Offers;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\OfferClicks;
use App\Models\ProductOffer;
use Exception;

/**
 * Returns the granular records that back each summary stat card shown in
 * the offer-listing component. Intentionally kept simple: load the relevant
 * offers (or clicks) with their relations, then let collection methods do
 * the shaping rather than pushing complexity into a query builder.
 *
 * Route (example):
 *   GET /api/{site}/offers/statistics/{type}
 *
 * Supported {type} values:
 *   total_offers   — every offer for the site
 *   pending        — offers with status = pending
 *   published      — offers with status = published
 *   rejected       — offers with status = rejected
 *   active         — currently active published offers (within date window)
 *   clicks         — all click records for the site's offers
 *   unique_clickers— one row per unique member_id that clicked
 */
class OfferStatisticsDetailController extends Controller
{
    private const ALLOWED_TYPES = [
        'total_offers',
        'pending',
        'published',
        'rejected',
        'active',
        'clicks',
        'unique_clickers',
    ];

    public function show(Request $request, string $siteName, string $type): JsonResponse
    {
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return $this->errorResponse('Unknown statistics type', 422);
        }

        try {
            $siteId = SiteContext::getId();

            return match ($type) {
                'clicks', 'unique_clickers' => $this->clickDetail($type, $siteId),
                default => $this->offerDetail($type, $siteId),
            };
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function clickDetail(string $type, int $siteId): JsonResponse
    {
        // Scope clicks to offers that belong to this site.
        $clicks = OfferClicks::with(['offer.product', 'member'])
            ->whereHas('offer.product', fn($q) => $q->where('site_id', $siteId))
            ->orderBy('clicked_at', 'desc')
            ->get();

        if ($type === 'unique_clickers') {
            // One row per unique member; pick the most recent click for context.
            $clicks = $clicks
                ->filter(fn($c) => $c->member_id !== null)
                ->groupBy('member_id')
                ->map(fn($group) => $group->sortByDesc('clicked_at')->first())
                ->values();
        }

        $rows = $clicks->map(fn($click) => [
            'id' => $click->id,
            'offer_id' => $click->offer_id,
            'product_name' => $click->offer?->product?->name,
            'member_id' => $click->member_id,
            'member_name' => $click->member?->name ?? 'Guest',
            'action' => $click->action,
            'ip_address' => $click->ip_address,
            'clicked_at' => $click->clicked_at?->format('Y-m-d H:i:s'),
        ]);

        return $this->resourceResponse([
            'success' => true,
            'type' => $type,
            'total' => $rows->count(),
            'items' => $rows,
        ]);
    }

    private function offerDetail(string $type, int $siteId): JsonResponse
    {
        // Load all offers for this site with the relations needed for display.
        // We intentionally load a single collection and filter in PHP to keep
        // queries simple and consistent with the existing service layer.
        $offers = ProductOffer::with(['product', 'merchant', 'publisher', 'rejector'])
            ->whereHas('product', fn($q) => $q->where('site_id', $siteId))
            ->orderBy('created_at', 'desc')
            ->get();

        $filtered = match ($type) {
            'pending' => $offers->where('status', 'pending'),
            'published' => $offers->where('status', 'published'),
            'rejected' => $offers->where('status', 'rejected'),
            'active' => $offers->filter(fn($o) => $o->isCurrentlyActive()),
            default => $offers, // total_offers
        };

        $rows = $filtered->values()->map(fn($offer) => [
            'id' => $offer->id,
            'product_name' => $offer->product?->name,
            'merchant_name' => $offer->merchant?->name,
            'status' => $offer->status,
            'sale_price' => $offer->sale_price,
            'original_price' => $offer->original_price,
            'discount_pct' => $offer->discount_percentage,
            'is_active' => $offer->is_active,
            'start_date' => $offer->start_date?->format('Y-m-d H:i:s'),
            'end_date' => $offer->end_date?->format('Y-m-d H:i:s'),
            'created_at' => $offer->created_at?->format('Y-m-d H:i:s'),
            // Who/when columns for status-specific types
            'published_at' => $offer->published_at?->format('Y-m-d H:i:s'),
            'published_by' => $offer->publisher['name'] ?? '',
            'rejected_at' => $offer->rejected_at?->format('Y-m-d H:i:s'),
            'rejected_by' => $offer->rejector['name'] ?? '',
            'rejection_reason' => $offer->rejection_reason,
        ]);

        return $this->resourceResponse([
            'success' => true,
            'type' => $type,
            'total' => $rows->count(),
            'items' => $rows,
        ]);
    }
}