<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Events\Subscriptions\SubscriptionCancelled;
use App\Events\Subscriptions\SubscriptionPaused;
use App\Events\Subscriptions\SubscriptionResumed;
use App\Framework\Authorization\Auth;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\MemberInsights\MemberActivityRepository;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
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
        private readonly IssueDeliveryRepository    $issueDeliveryRepository,
        private readonly IssuesDeliveredRepository  $issuesDeliveredRepository,
        private readonly PaymentRepository          $paymentRepository,
        private readonly SubscriptionPlanRepository $planRepository,
        private readonly OrderRepository          $orderRepository,
        private readonly MemberActivityRepository $activityRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/admin/subscriptions/{subscriptionId}/history
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
     * Request body:
     *   plan_id               int     required
     *   payment_method_id     string  required  — Stripe pm_xxx
     *   delivery_address_id   int     optional  — existing member address (print plans)
     *   delivery_address      array   optional  — new address object (print plans)
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

        // Resolve optional delivery address for print plans
        $deliveryAddressId = $request->input('delivery_address_id')
            ? (int)$request->input('delivery_address_id')
            : null;

        $deliveryAddress = $request->input('delivery_address')
            ? (array)$request->input('delivery_address')
            : null;

        try {
            $result = $this->creationService->createSubscription(
                memberId: $memberId,
                planId: $planId,
                paymentMethodId: $paymentMethodId,
                siteId: $siteId,
                deliveryAddressId: $deliveryAddressId,
                deliveryAddress: $deliveryAddress,
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

    /**
     * POST /api/{site}/admin/members/{memberId}/subscriptions/{subscriptionId}/cancel
     *
     * Request body:
     *   cancel_at_period_end  bool    optional (default true)
     *   reason                string  optional — cancellation reason / note
     */
    public function cancelForMember(Request $request, int $memberId, int $subscriptionId): mixed
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $memberId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $cancelAtPeriodEnd = (bool)$request->input('cancel_at_period_end', true);
        $reason = trim((string)$request->input('reason', ''));
        $issueRefund = !$cancelAtPeriodEnd && (bool)$request->input('issue_refund', false);

        $result = $this->cancellationService->cancelSubscription($subscriptionId, [
            'cancel_at_period_end' => $cancelAtPeriodEnd,
            'reason' => $reason ?: null,
            'create_refund' => $issueRefund,
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
            pausedUntil: $pauseEnd->format('Y-m-d H:i:s'),
            pauseStart: $pauseStart->format('Y-m-d H:i:s'),
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

    // =========================================================================
    // Issues & Deliveries
    // =========================================================================

    /**
     * GET /api/{site}/admin/members/{memberId}/subscriptions/{subscriptionId}/issues
     *
     * Query params:
     *   filter    string  all|upcoming|previous|missed  (default: all)
     *   from      string  Y-m-d  optional
     *   to        string  Y-m-d  optional
     *   page      int     (default: 1)
     *   per_page  int     (default: 15, max: 50)
     *
     * Returns a merged, chronologically-sorted list combining:
     *   - IssueDelivery (scheduled/upcoming deliveries from the issue schedule)
     *   - IssuesDelivered (historical delivery records per subscriber)
     *
     * Each row includes a `type` field: upcoming | delivered | missed
     */
    public function issuesForSubscription(Request $request, int $memberId, int $subscriptionId): mixed
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $subscription = $this->subscriptionRepository->find($subscriptionId, ['plan']);

        if (!$subscription || $subscription->member_id !== $memberId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $filter = $request->input('filter', 'all');
        $fromRaw = $request->input('from');
        $toRaw = $request->input('to');
        $page = max(1, (int)$request->input('page', 1));
        $perPage = min(50, max(1, (int)$request->input('per_page', 15)));

        try {
            $result = $this->issueDeliveryRepository->getPaginatedForSubscription(
                planId: $subscription->plan_id,
                subscriptionId: $subscriptionId,
                filter: $filter,
                from: $fromRaw ? new \DateTime($fromRaw) : null,
                to: $toRaw ? new \DateTime($toRaw) : null,
                page: $page,
                perPage: $perPage,
            );

            return $this->resourceResponse([
                'success' => true,
                'issues' => $result['data'],
                'pagination' => [
                    'total' => $result['total'],
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => $result['last_page'],
                ],
            ]);
        } catch (\Exception $e) {
            echo $e->getMessage();
            Logger::error('Failed to fetch issues for subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse(['success' => false, 'message' => 'Failed to load issues.'], 500);
        }
    }

    // =========================================================================
    // Payments
    // =========================================================================

    /**
     * GET /api/{site}/admin/members/{memberId}/payments
     *
     * Query params:
     *   context  string  all|subscription|orders  (default: all)
     *    page      int     (default: 1)
     *    per_page  int     (default: 15, max: 50)
     *
     * Returns all payments associated with the member, sourced from both
     * subscription payments and order payments. Uses PaymentRepository
     * methods that are already in place.
     */
    public function paymentsForMember(Request $request, int $memberId): mixed
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $siteId = SiteContext::getId();
        $context = $request->input('context', 'all');
        $page = max(1, (int)$request->input('page', 1));
        $perPage = min(50, max(1, (int)$request->input('per_page', 15)));

        try {
            $payments = collect();

            if (in_array($context, ['all', 'subscription'], true)) {
                // Single query for all subscription payments for this member on this site
                $subPayments = $this->paymentRepository->findByMemberSubscriptions($memberId, $siteId);
                $payments = $payments->merge($subPayments);
            }

            if (in_array($context, ['all', 'orders'], true)) {
                // Single query for all order payments for this member, excluding
                // those already captured via a subscription_id to avoid duplication
                $orderPayments = $this->paymentRepository->findByMemberOrders($memberId);
                $payments = $payments->merge($orderPayments);
            }

            $sorted = $payments
                ->unique('id')
                ->sortByDesc('created_at')
                ->values();

            $total = $sorted->count();
            $lastPage = (int)ceil($total / $perPage);
            $slice = $sorted->forPage($page, $perPage);

            $rows = $slice->map(fn($p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'currency' => $p->currency ?? 'GBP',
                'status' => $p->status,
                'payment_method' => $p->payment_method,
                'paid_at' => $p->paid_at?->format('Y-m-d H:i:s'),
                'created_at' => $p->created_at->format('Y-m-d H:i:s'),
                'order_id' => $p->order_id,
                'subscription_id' => $p->subscription_id,
            ])->values()->all();

            return $this->resourceResponse([
                'success' => true,
                'payments' => $rows,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => $lastPage,
                ],
            ]);
        } catch (\Exception $e) {
            echo $e->getMessage();
            die;
            Logger::error('Failed to fetch payments for member', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse(['success' => false, 'message' => 'Failed to load payments.'], 500);
        }
    }

    /**
     * GET /api/{site}/admin/subscriptions/plans/{planId}
     *
     * Returns full plan details for display in the CRM member detail drawer.
     * Scoped to the current site — prevents cross-site plan leakage.
     */
    public function getPlan(Request $request, int $planId): mixed
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $siteId = SiteContext::getId();

        $plan = $this->planRepository->findWithPricingTiers($planId);

        if (!$plan || $plan->site_id !== $siteId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Plan not found.'], 404);
        }

        return $this->resourceResponse([
            'success' => true,
            'plan' => $plan->toArray(),
        ]);
    }

    /**
     * GET /api/{site}/admin/members/{memberId}/subscription-stats
     *
     * Returns aggregate subscription stats for the CRM member detail panel:
     *   active_count      int          — active + trialing subscriptions
     *   cancelled_count   int          — cancelled subscriptions
     *   last_payment_date string|null  — most recent completed payment date
     *   next_payment_date string|null  — earliest next_billing_date on active subs
     */
    public function subscriptionStatsForMember(Request $request, int $memberId): mixed
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $siteId = SiteContext::getId();

        $subscriptions = $this->subscriptionRepository->getSubscriptionHistory($memberId, $siteId);

        $activeCount = $subscriptions->filter(fn($s) => in_array($s->status, ['active', 'trialing']))->count();
        $cancelledCount = $subscriptions->filter(fn($s) => $s->status === 'cancelled')->count();

        // Last completed payment across all subscriptions for this member
        $lastPaymentDate = null;
        foreach ($subscriptions as $sub) {
            $payment = $this->paymentRepository->getLastSubscriptionPayment($sub->id);
            if ($payment?->paid_at) {
                $candidate = $payment->paid_at->format('Y-m-d H:i:s');
                if ($lastPaymentDate === null || $candidate > $lastPaymentDate) {
                    $lastPaymentDate = $candidate;
                }
            }
        }

        // Earliest upcoming billing date across active/trialing subscriptions
        $nextPaymentDate = $subscriptions
            ->filter(fn($s) => in_array($s->status, ['active', 'trialing']) && $s->next_billing_date)
            ->map(fn($s) => $s->next_billing_date instanceof \DateTimeInterface
                ? $s->next_billing_date->format('Y-m-d H:i:s')
                : (string)$s->next_billing_date)
            ->sort()
            ->first();

        return $this->resourceResponse([
            'success' => true,
            'active_count' => $activeCount,
            'cancelled_count' => $cancelledCount,
            'last_payment_date' => $lastPaymentDate,
            'next_payment_date' => $nextPaymentDate ?? null,
        ]);
    }

    /**
     * GET /api/{site}/crm/members/{memberId}/orders
     *
     * Query params:
     *   page      int  (default: 1)
     *   per_page  int  (default: 15, max: 50)
     *
     * Returns orders for a member, paginated, newest first.
     * Scoped to the current site.
     */
    public function ordersForMember(Request $request, int $memberId): mixed
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $siteId = SiteContext::getId();
        $page = max(1, (int)$request->input('page', 1));
        $perPage = min(50, max(1, (int)$request->input('per_page', 15)));

        try {
            $result = $this->orderRepository->getPaginatedForMember(
                memberId: $memberId,
                siteId: $siteId,
                page: $page,
                perPage: $perPage,
            );

            $rows = $result['data']->map(fn($order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => $order->total,
                'currency' => $order->currency ?? 'GBP',
                'item_count' => $order->item_count ?? 0,
                'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
            ])->all();

            return $this->resourceResponse([
                'success' => true,
                'orders' => $rows,
                'pagination' => [
                    'total' => $result['total'],
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => $result['last_page'],
                ],
            ]);
        } catch (\Exception $e) {
            echo $e->getMessage();
            die;
            Logger::error('Failed to fetch orders for member', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse(['success' => false, 'message' => 'Failed to load orders.'], 500);
        }
    }

    /**
     * GET /api/{site}/crm/members/{memberId}/activity
     *
     * Query params:
     *   page      int  (default: 1)
     *   per_page  int  (default: 15, max: 50)
     *
     * Returns recent member activity events, paginated, newest first.
     */
    public function activityForMember(Request $request, int $memberId): mixed
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $siteId = SiteContext::getId();
        $page = max(1, (int)$request->input('page', 1));
        $perPage = min(50, max(1, (int)$request->input('per_page', 15)));

        // Verify member belongs to site before exposing data
        $member = $this->memberRepository->find($memberId);

        if (!$member || $member->site_id !== $siteId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Member not found.'], 404);
        }

        try {
            $result = $this->activityRepository->getPaginatedForMember(
                memberId: $memberId,
                page: $page,
                perPage: $perPage,
            );

            $rows = $result['data']->map(fn($act) => [
                'id' => $act->id,
                'activity_type' => $act->activity_type,
                'points' => $act->points ?? null,
                'metadata' => $act->metadata ?? null,
                'activity_date' => $act->activity_date?->format('Y-m-d H:i:s')
                    ?? $act->created_at?->format('Y-m-d H:i:s'),
            ])->all();

            return $this->resourceResponse([
                'success' => true,
                'activities' => $rows,
                'pagination' => [
                    'total' => $result['total'],
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => $result['last_page'],
                ],
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to fetch activity for member', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse(['success' => false, 'message' => 'Failed to load activity.'], 500);
        }
    }
}