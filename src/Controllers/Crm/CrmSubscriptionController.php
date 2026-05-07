<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Events\Subscriptions\SubscriptionCancelled;
use App\Events\Subscriptions\SubscriptionPaused;
use App\Events\Subscriptions\SubscriptionResumed;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Models\IssueDelivery;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\CrmSubscriptionCreationService;
use App\Services\Subscriptions\SubscriptionCancellationService;
use App\Services\Subscriptions\SubscriptionDeliveryService;
use App\Services\Subscriptions\SubscriptionHistoryService;

class CrmSubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionRepository          $subscriptionRepository,
        private readonly MemberRepository                $memberRepository,
        private readonly CrmSubscriptionCreationService  $creationService,
        private readonly SubscriptionHistoryService      $historyService,
        private readonly SubscriptionCancellationService $cancellationService,
        private readonly SubscriptionDeliveryService     $deliveryService,
        private readonly PaymentRepository               $paymentRepository
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/admin/subscriptions/{subscriptionId}/history
     *
     * Paginated lifecycle event log for a single subscription.
     */
    public function history(Request $request, int $subscriptionId): mixed
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $siteId = SiteContext::getId();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->site_id !== $siteId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $page = max(1, (int)$request->input('page', 1));
        $perPage = min(50, max(1, (int)$request->input('per_page', 10)));

        try {
            $result = $this->historyService->getPaginatedHistory($subscriptionId, $page, $perPage);

            return $this->resourceResponse([
                'success' => true,
                'events' => $result['events'],
                'total' => $result['total'],
                'page' => $page,
                'per_page' => $perPage,
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to fetch subscription history', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse(['success' => false, 'message' => 'Failed to load history.'], 500);
        }
    }

    /**
     * POST /api/{site}/admin/members/{memberId}/subscriptions
     *
     * Creates a new subscription for a member. Delegates entirely to
     * AdminSubscriptionCreationService which in turn uses
     * OneTimeSubscriptionCheckoutService::processCheckout().
     *
     * Request body:
     *   plan_id            int     required
     *   payment_method_id  string  required  — Stripe pm_xxx
     */
    public function createForMember(Request $request, int $memberId): mixed
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $siteId = SiteContext::getId();
        $planId = (int)$request->input('plan_id');
        $paymentMethodId = trim((string)$request->input('payment_method_id', ''));

        if (!$planId) {
            return $this->jsonResponse(['success' => false, 'message' => 'plan_id is required.'], 422);
        }

        if ($paymentMethodId === '') {
            return $this->jsonResponse(['success' => false, 'message' => 'payment_method_id is required.'], 422);
        }

        try {
            $result = $this->creationService->createSubscription(
                memberId: $memberId,
                planId: $planId,
                paymentMethodId: $paymentMethodId,
                siteId: $siteId,
            );

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Subscription created successfully.',
                'subscription' => $result['subscription'],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Logger::error('Admin failed to create subscription for member', [
                'member_id' => $memberId,
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function cancelForMember(Request $request, int $memberId, int $subscriptionId): mixed
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $memberId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $cancelAtPeriodEnd = (bool)$request->input('cancel_at_period_end', true);

        $result = $this->cancellationService->cancelSubscription($subscriptionId, [
            'cancel_at_period_end' => $cancelAtPeriodEnd,
        ]);

        if (!$result['success']) {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to cancel subscription.'], 500);
        }

        event(new SubscriptionCancelled(
            subscriptionId: $subscription->id,
            cancelAtPeriodEnd: $cancelAtPeriodEnd,
            endDate: now(),
        ));

        return $this->resourceResponse([
            'success' => true,
            'message' => $cancelAtPeriodEnd
                ? 'Subscription will be cancelled at the end of the billing period.'
                : 'Subscription cancelled successfully.',
            'subscription' => $result['subscription'],
        ]);
    }

    public function pauseDeliveryForMember(Request $request, int $memberId, int $subscriptionId): mixed
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $memberId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $pauseStart = new \DateTime($request->input('pause_start'));
        $pauseEnd = new \DateTime($request->input('pause_end'));
        $reason = $request->input('reason');

        $result = $this->deliveryService->pauseDelivery($subscriptionId, $pauseStart, $pauseEnd, $reason);

        event(new SubscriptionPaused(
            subscription: $subscription,
            pauseStart: $pauseStart->format('Y-m-d H:i:s'),
            pausedUntil: $pauseEnd->format('Y-m-d H:i:s'),
            reason: $reason,
        ));

        return $this->resourceResponse($result);
    }

    public function resumeDeliveryForMember(Request $request, int $memberId, int $subscriptionId): mixed
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $memberId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $result = $this->deliveryService->resumeDelivery($subscriptionId);

        event(new SubscriptionResumed(
            subscription: $subscription,
            memberId: $memberId,
        ));

        return $this->resourceResponse($result);
    }

    public function payments(Request $request): JsonResponse
    {
        $payments = $this->paymentRepository
            ->all()
            ->map(function ($payment) {

                $format = fn($date) => $date instanceof \DateTimeInterface
                    ? $date->format('Y-m-d H:i:s')
                    : $date;

                return array_merge($payment->toArray(), [
                    'paid_at' => $format($payment->paid_at ?? null),
                    'failed_at' => $format($payment->failed_at ?? null),
                    'created_at' => $format($payment->created_at ?? null),
                    'updated_at' => $format($payment->updated_at ?? null),
                ]);
            });

        return $this->resourceResponse([
            'success' => true,
            'payments' => $payments,
        ]);
    }

    public function reactivateForMember(Request $request, int $memberId, int $subscriptionId): mixed
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $memberId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $result = $this->cancellationService->reactivateSubscription($subscriptionId);

        if (!$result['success']) {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to reactivate subscription.'], 500);
        }

        return $this->resourceResponse([
            'success' => true,
            'message' => 'Subscription reactivated successfully.',
            'subscription' => $result['subscription'],
        ]);
    }

    public function issues(Request $request): JsonResponse
    {
        $format = fn($date) => $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d H:i:s')
            : $date;

        $issues = IssueDelivery::all()->map(function ($issue) use ($format) {
            return array_merge($issue->toArray(), [
                'on_sale_date' => $format($issue->on_sale_date ?? null),
                'estimated_delivery_date' => $format($issue->estimated_delivery_date ?? null),
                'cut_off_date' => $format($issue->cut_off_date ?? null),
                'fulfilment_date' => $format($issue->fulfilment_date ?? null),
                'restock_date' => $format($issue->restock_date ?? null),
                'dispatched_at' => $format($issue->dispatched_at ?? null),
            ]);
        });

        return $this->resourceResponse([
            'issues' => $issues,
        ]);
    }
}