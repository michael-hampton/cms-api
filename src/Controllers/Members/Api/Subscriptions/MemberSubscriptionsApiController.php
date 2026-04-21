<?php

namespace App\Controllers\Members\Api\Subscriptions;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\MemberSubscriptionService;
use App\Services\Subscriptions\SubscriptionBillingService;
use App\Services\Subscriptions\SubscriptionCancellationService;
use App\Services\Subscriptions\SubscriptionDeliveryService;
use App\Services\Subscriptions\SubscriptionPlanService;

class MemberSubscriptionsApiController extends Controller
{
    public function __construct(
        private readonly SubscriptionRepository          $subscriptionRepository,
        private readonly MemberSubscriptionService       $subscriptionService,
        private readonly SubscriptionCancellationService $cancellationService,
        private readonly SubscriptionBillingService      $billingService,
        private readonly SubscriptionDeliveryService     $deliveryService,
        private readonly SubscriptionPlanService         $planService,
    )
    {
        parent::__construct();
    }

    public function overview(): mixed
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $activeSubscription = $this->subscriptionRepository->getActiveSubscriptionForMember($member->id, $siteId, true);

        $subscriptionHistory = $this->subscriptionRepository->getSubscriptionHistory($member->id, $siteId);
        $subscriptionSummary = $this->subscriptionService->getSubscriptionSummary($member->id, $siteId);
        $plans = $this->planService->getActivePlansForSite($siteId);

        return $this->resourceResponse([
            'success' => true,
            'activeSubscription' => !empty($activeSubscription) ? array_merge(
                $activeSubscription->toArray(), [
                'start_date' => $activeSubscription->start_date?->format('Y-m-d H:i:s'),
                'end_date' => $activeSubscription->end_date?->format('Y-m-d H:i:s'),
                'download_expires_at' => $activeSubscription->download_expires_at?->format('Y-m-d H:i:s'),
                'delivery_pause_start' => $activeSubscription->delivery_pause_start?->format('Y-m-d H:i:s'),
                'delivery_pause_end' => $activeSubscription->delivery_pause_end?->format('Y-m-d H:i:s'),
            ]) : [],
            'subscriptionHistory' => $subscriptionHistory,
            'subscriptionSummary' => $subscriptionSummary,
            'plans' => $plans,
        ]);
    }

    public function cancel(Request $request, int $subscriptionId): mixed
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $member = MemberAuth::getMember();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        try {
            $cancelAtPeriodEnd = $request->input('cancel_at_period_end', true);

            $result = $this->cancellationService->cancelSubscription($subscriptionId, [
                'cancel_at_period_end' => $cancelAtPeriodEnd,
            ]);

            if (!$result['success']) {
                return $this->jsonResponse(['success' => false, 'message' => 'Failed to cancel subscription.'], 500);
            }

            $message = $cancelAtPeriodEnd
                ? 'Subscription will be cancelled at the end of the billing period.'
                : 'Subscription cancelled successfully.';

            return $this->resourceResponse([
                'success' => true,
                'message' => $message,
                'subscription' => $result['subscription'],
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to cancel subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function reactivate(Request $request, int $subscriptionId): mixed
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $member = MemberAuth::getMember();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        try {
            $result = $this->cancellationService->reactivateSubscription($subscriptionId);

            if (!$result['success']) {
                return $this->jsonResponse(['success' => false, 'message' => 'Failed to reactivate subscription.'], 500);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Subscription reactivated successfully.',
                'subscription' => $result['subscription'],
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to reactivate subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function autoRenew(Request $request, int $subscriptionId): mixed
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $member = MemberAuth::getMember();
        $autoRenew = (bool)$request->input('auto_renew');
        $consentGiven = (bool)$request->input('consent_given', false);

        try {
            $result = $this->subscriptionService->updateAutoRenew(
                $subscriptionId,
                $member->id,
                $autoRenew,
                $consentGiven,
            );

            return $this->resourceResponse([
                'success' => true,
                'message' => $autoRenew ? 'Auto-renewal enabled.' : 'Auto-renewal disabled.',
                'auto_renew' => $result['auto_renew'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\RuntimeException $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Logger::error('Failed to update auto-renewal', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return $this->resourceResponse(['success' => false, 'message' => 'Failed to update auto-renewal.'], 500);
        }
    }

    public function updateBillingDate(Request $request, int $subscriptionId): mixed
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $member = MemberAuth::getMember();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $dayOfMonth = (int)$request->input('day_of_month');

        if ($dayOfMonth < 1 || $dayOfMonth > 31) {
            return $this->resourceResponse(['success' => false, 'message' => 'Please select a day between 1 and 31.'], 400);
        }

        try {
            $result = $this->billingService->updateBillingDate($subscriptionId, $dayOfMonth);

            if ($result['success']) {
                return $this->resourceResponse([
                    'success' => true,
                    'message' => 'Billing date updated successfully.',
                    'new_billing_date' => $result['new_billing_date'],
                ]);
            }

            return $this->resourceResponse([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to update billing date.',
            ], 500);
        } catch (\Exception $e) {
            Logger::error('Failed to update billing date', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function previewBillingDateChange(Request $request, int $subscriptionId): mixed
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $member = MemberAuth::getMember();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->resourceResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $dayOfMonth = (int)$request->input('day_of_month');

        if ($dayOfMonth < 1 || $dayOfMonth > 31) {
            return $this->resourceResponse(['success' => false, 'message' => 'Please select a day between 1 and 31.'], 400);
        }

        try {
            $preview = $this->billingService->previewBillingDateChange($subscriptionId, $dayOfMonth);

            if ($preview['success']) {
                return $this->resourceResponse(['success' => true, 'data' => $preview]);
            }

            return $this->resourceResponse([
                'success' => false,
                'message' => $preview['message'] ?? 'Failed to preview billing date change.',
            ], 500);
        } catch (\Exception $e) {
            return $this->resourceResponse(['success' => false, 'message' => 'Failed to preview billing date change.'], 500);
        }
    }

    public function pauseDelivery(Request $request, int $subscriptionId): mixed
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $member = MemberAuth::getMember();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->resourceResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        try {
            $pauseStart = new \DateTime($request->input('pause_start'));
            $pauseEnd = new \DateTime($request->input('pause_end'));
            $reason = $request->input('reason');

            $result = $this->deliveryService->pauseDelivery($subscriptionId, $pauseStart, $pauseEnd, $reason);

            return $this->resourceResponse($result);
        } catch (\Exception $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function resumeDelivery(Request $request, int $subscriptionId): mixed
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $member = MemberAuth::getMember();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->resourceResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        try {
            $result = $this->deliveryService->resumeDelivery($subscriptionId);

            return $this->resourceResponse($result);
        } catch (\Exception $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}