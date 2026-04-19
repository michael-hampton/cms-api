<?php

namespace App\Controllers\MemberInsights;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Repositories\MemberInsights\CampaignDeliveryRepository;
use App\Repositories\MemberInsights\CampaignEventRepository;

/**
 * Ticket 11 — Campaign Tracking Endpoints.
 *
 * GET /campaign/track/open/{deliveryToken}
 *   Returns a 1×1 transparent GIF (email pixel) and records the open event.
 *
 * GET /campaign/track/click/{deliveryToken}?url=...&block=...
 *   Records the click event then redirects to the target URL.
 *
 * Design:
 *   - deliveryToken is a unique opaque token stored in campaign_deliveries.token
 *   - No authentication required (the token IS the identity proof)
 *   - Non-critical: tracking failures must NOT break redirects
 *   - The pixel endpoint always returns 200 even if tracking fails
 */
class CampaignTrackingController extends Controller
{
    /** 1×1 transparent GIF — smallest valid tracking pixel. */
    private const TRACKING_PIXEL = "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00\x21\xf9\x04\x00\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3b";

    public function __construct(
        private readonly CampaignDeliveryRepository $deliveryRepository,
        private readonly CampaignEventRepository    $eventRepository,
    )
    {
        parent::__construct();
    }

    /**
     * Email open pixel.
     * Always returns the pixel GIF regardless of tracking success.
     */
    public function open(Request $request, string $deliveryToken): mixed
    {
        try {
            $delivery = $this->resolveDelivery($deliveryToken);
            if ($delivery !== null) {
                $this->eventRepository->recordOpen(
                    $delivery->member_id,
                    $delivery->campaign_id,
                    $delivery->variant_id ?? null,
                );
            }
        } catch (\Throwable) {
            // Non-critical — never block the pixel response.
        }

        header('Content-Type: image/gif');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo self::TRACKING_PIXEL;
        exit;
    }

    private function resolveDelivery(string $token): ?object
    {
        return \App\Models\CampaignDelivery::where('token', $token)->first();
    }

    // -------------------------------------------------------------------------

    /**
     * Tracked link click — record event then redirect.
     */
    public function click(Request $request, string $deliveryToken): mixed
    {
        $targetUrl = $request->input('url', '/');
        $blockKey = $request->input('block');

        try {
            $delivery = $this->resolveDelivery($deliveryToken);
            if ($delivery !== null) {
                $this->eventRepository->recordClick(
                    memberId: $delivery->member_id,
                    campaignId: $delivery->campaign_id,
                    url: $targetUrl,
                    blockKey: $blockKey ?: null,
                    variantId: $delivery->variant_id ?? null,
                );
            }
        } catch (\Throwable) {
            // Non-critical — always redirect even if tracking fails.
        }

        return $this->redirect($targetUrl);
    }
}