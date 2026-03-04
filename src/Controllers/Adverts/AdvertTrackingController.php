<?php

namespace App\Controllers\Adverts;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\RedirectResponse;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Adverts\DealTrackingRecorder;
use App\Services\Adverts\RenderContext;

/**
 * Handles server-side redirect tracking for advert clicks.
 *
 * Route: GET /go/{type}/{id}
 *   type: offer | deal | reward
 *
 * Flow:
 *   1. Resolve the entity and its destination URL
 *   2. Record the click (non-critical — catch and log on failure)
 *   3. Redirect to destination
 *
 * Boosts are intentionally excluded — they delegate tracking
 * to the underlying offer/product at render time only.
 */
class AdvertTrackingController extends Controller
{
    public function __construct(
        private readonly ProductOfferRepository $offerRepository,
        private readonly ProductRepository      $productRepository,
        private readonly RewardsRepository      $rewardsRepository,
        private readonly DealTrackingRecorder   $trackingRecorder,
        private readonly Logger                 $logger,
    )
    {
        parent::__construct();
    }

    public function redirectFromAdvert(Request $request, string $type, int $id): RedirectResponse|Response
    {
        return match ($type) {
            'offer' => $this->handleOffer($request, $id),
            'deal' => $this->handleDeal($request, $id),
            'reward' => $this->handleReward($request, $id),
            default => $this->abort(404),
        };
    }

    private function handleOffer(Request $request, int $offerId): RedirectResponse|Response
    {
        $offer = $this->offerRepository->find($offerId);

        if (!$offer || !$offer->link) {
            $this->abort(404);
        }

        $context = $this->buildContext($request, 'offer', $offerId);

        try {
            $this->trackingRecorder->recordOfferClick(
                offerId: $offerId,
                dealId: null,
                context: $context,
                ip: $request->ip() ?? '',
                userAgent: $request->userAgent() ?? '',
            );
        } catch (\Throwable $e) {
            $this->logger->error('AdvertTrackingController: offer click tracking failed', [
                'offer_id' => $offerId,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->redirect($offer->link);
    }

    /**
     * Builds a minimal RenderContext from the inbound request.
     * memberId is nullable — guests are tracked with null.
     * surface_type/surface_id are passed as query params by the renderer.
     */
    private function buildContext(Request $request, string $type, int $id): RenderContext
    {
        /** @var \App\Models\Member|null $member */
        $member = MemberAuth::getMember();

        return new RenderContext(
            memberId: $member?->id,
            plan: $member?->plan ?? '',
            isPaid: $member?->isPaid() ?? false,
            channel: $request->query('channel', 'web'),
            surfaceType: $request->query('surface_type', ''),
            surfaceId: (int)$request->query('surface_id', 0),
            timestamp: now_datetime(),
        );
    }

    private function handleDeal(Request $request, int $productId): RedirectResponse|Response
    {
        $product = $this->productRepository->find($productId);

        if (!$product || !$product->slug) {
            $this->abort(404);
        }

        $destination = url('/' . SiteContext::slug() . '/shop/details/' . $product->slug);

        $context = $this->buildContext($request, 'deal', $productId);

        try {

            $this->trackingRecorder->recordDealClick(
                productId: $productId,
                context: $context,
                ip: $request->ip() ?? '',
                userAgent: $request->userAgent() ?? '',
                siteId: SiteContext::getId(),
            );
        } catch (\Throwable $e) {
            $this->logger->error('AdvertTrackingController: deal click tracking failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->redirect($destination);
    }

    private function handleReward(Request $request, int $rewardId): RedirectResponse|Response
    {
        $reward = $this->rewardsRepository->findMemberRewardById($rewardId);

        if (!$reward) {
            $this->abort(404);
        }

        // Rewards redirect to the internal member reward page — no external link needed
        $destination = url('/member/rewards/' . $rewardId);

        $context = $this->buildContext($request, 'reward', $rewardId);

        try {
            $this->trackingRecorder->recordRewardClick(
                rewardId: $rewardId,
                dealId: null,
                context: $context,
                ip: $request->ip() ?? '',
                userAgent: $request->userAgent() ?? '',
                siteId: SiteContext::getId(),
            );
        } catch (\Throwable $e) {
            $this->logger->error('AdvertTrackingController: reward click tracking failed', [
                'reward_id' => $rewardId,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->redirect($destination);
    }

    public function handle(Request $request, string $type, int $id): RedirectResponse|Response
    {
        return match ($type) {
            'offer' => $this->handleOffer($request, $id),
            'deal' => $this->handleDeal($request, $id),
            'reward' => $this->handleReward($request, $id),
            default => $this->abort(404),
        };
    }
}