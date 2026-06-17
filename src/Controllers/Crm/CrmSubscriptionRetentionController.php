<?php

declare(strict_types=1);

namespace App\Controllers\Crm;

use App\Controllers\Concerns\RequiresSitePermission;
use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionRetentionIncentiveService;

final class CrmSubscriptionRetentionController extends Controller
{
    use RequiresSitePermission;

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionRetentionIncentiveService $retentionService,
    ) {
        parent::__construct();
    }

    public function apply(Request $request, int $memberId, int $subscriptionId): mixed
    {
        if ($response = $this->requireSitePermission('crm.subscriptions.cancel')) {
            return $response;
        }

        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || (int)$subscription->member_id !== $memberId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $type = trim((string)$request->input('type', ''));
        $reason = trim((string)$request->input('reason', '')) ?: null;

        try {
            $updated = match ($type) {
                'offer' => $this->retentionService->applyOffer(
                    subscriptionId: $subscriptionId,
                    pricingId: (int)$request->input('pricing_id'),
                    offerType: trim((string)$request->input('offer_type', '')),
                    reason: $reason,
                ),
                'voucher' => $this->retentionService->applyVoucher(
                    subscriptionId: $subscriptionId,
                    voucherCode: trim((string)$request->input('voucher_code', '')),
                    reason: $reason,
                ),
                default => throw new \InvalidArgumentException('type must be offer or voucher.'),
            };
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Logger::error('Failed to apply subscription retention incentive', [
                'member_id' => $memberId,
                'subscription_id' => $subscriptionId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return $this->resourceResponse([
            'success' => true,
            'message' => 'Retention incentive applied. Subscription remains active.',
            'subscription' => $updated,
        ]);
    }
}
