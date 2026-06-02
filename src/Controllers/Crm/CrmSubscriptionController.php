<?php

namespace App\Controllers\Crm;

use App\Actions\Subscriptions\SuspendSubscriptionAction;
use App\Controllers\Controller;
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
use App\Services\Subscriptions\FulfilmentReplacementService;
use App\Services\Subscriptions\Refunds\RefundResult;
use App\Services\Subscriptions\Refunds\RefundStrategy;
use App\Services\Subscriptions\SubscriptionCancellationService;
use App\Services\Subscriptions\SubscriptionDeliveryService;
use App\Services\Subscriptions\SubscriptionHistoryService;
use App\Services\Subscriptions\SubscriptionProductSwitchService;
use App\Services\Subscriptions\SubscriptionRefundService;
use App\Services\Subscriptions\SubscriptionRenewalService;

class CrmSubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionRepository          $subscriptionRepository,
        private readonly MemberRepository                $memberRepository,
        private readonly CrmSubscriptionCreationService  $creationService,
        private readonly SubscriptionHistoryService      $historyService,
        private readonly SubscriptionCancellationService $cancellationService,
        private readonly SubscriptionDeliveryService     $deliveryService,
        private readonly IssueDeliveryRepository          $issueDeliveryRepository,
        private readonly IssuesDeliveredRepository        $issuesDeliveredRepository,
        private readonly PaymentRepository                $paymentRepository,
        private readonly SubscriptionPlanRepository       $planRepository,
        private readonly OrderRepository                  $orderRepository,
        private readonly MemberActivityRepository         $activityRepository,
        // ── New services ───────────────────────────────────────────────────
        private readonly SubscriptionRenewalService       $renewalService,
        private readonly SubscriptionProductSwitchService $productSwitchService,
        private readonly FulfilmentReplacementService     $replacementService,
        private readonly SubscriptionRefundService        $refundService,
        private readonly SuspendSubscriptionAction        $suspendAction,
    )
    {
        parent::__construct();
    }

    // =========================================================================
    // Existing endpoints (unchanged)
    // =========================================================================

    /** GET /api/{site}/crm/subscriptions/{subscriptionId}/history */
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
     * POST /api/{site}/crm/members/{memberId}/subscriptions
     *
     * Body: plan_id, payment_method_id, delivery_address_id?, delivery_address?
     */
    public function createForMember(Request $request, int $memberId): mixed
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $siteId = SiteContext::getId();
        $planId = (int)$request->input('plan_id');
        $paymentMethodId = trim((string)$request->input('payment_method_id', ''));
        $pricingId = $request->input('pricing_id') ? (int)$request->input('pricing_id') : null;
        $offerType = trim((string)$request->input('offer_type', '')) ?: null;

        if (!$planId) {
            return $this->jsonResponse(['success' => false, 'message' => 'plan_id is required.'], 422);
        }

        if ($paymentMethodId === '') {
            return $this->jsonResponse(['success' => false, 'message' => 'payment_method_id is required.'], 422);
        }

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
                pricingId: $pricingId,
                offerType: $offerType,
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
     * POST /api/{site}/crm/members/{memberId}/subscriptions/{subscriptionId}/cancel
     *
     * Body: cancel_at_period_end?, reason?, issue_refund?
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

        $options = [
            'cancel_at_period_end' => $cancelAtPeriodEnd,
            'reason' => $reason ?: null,
            'create_refund' => $issueRefund,
            'refund_type' => $request->input('refund_type', 'pro_rated'),
        ];

        // A specific override amount may be supplied by the CRM agent.
        // Presence of refund_amount causes ManualRefundStrategy to be used regardless
        // of refund_type — validated as a positive float before forwarding.
        $rawAmount = $request->input('refund_amount');
        if ($rawAmount !== null) {
            $refundAmount = (float)$rawAmount;
            if ($refundAmount <= 0) {
                return $this->errorResponse('refund_amount must be greater than zero.',
                    422
                );
            }
            $options['refund_amount'] = $refundAmount;
            // Treat an explicit amount as an implicit instruction to issue a refund.
            $options['create_refund'] = true;
        }

        try {
            $result = $this->cancellationService->cancelSubscription($subscriptionId, $options);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Logger::error('Failed to cancel subscription', [
                'subscription_id' => $subscriptionId,
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }

        if (!$result['success']) {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to cancel subscription.'], 500);
        }

        return $this->resourceResponse([
            'success' => true,
            'message' => $cancelAtPeriodEnd
                ? 'Subscription will be cancelled at the end of the billing period.'
                : 'Subscription cancelled immediately.',
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

        return $this->resourceResponse($result);
    }

    public function resumeDeliveryForMember(Request $request, int $memberId, int $subscriptionId): mixed
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $memberId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $result = $this->deliveryService->resumeDelivery($subscriptionId);

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
     * GET /api/{site}/crm/members/{memberId}/subscriptions/{subscriptionId}/issues
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
            Logger::error('Failed to fetch issues for subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse(['success' => false, 'message' => 'Failed to load issues.'], 500);
        }
    }

    // =========================================================================
    // NEW — Ticket 1: Subscription Renewal
    // =========================================================================

    /**
     * POST /api/{site}/crm/members/{memberId}/subscriptions/{subscriptionId}/renew
     *
     * Body:
     *   plan_id            int     required — plan for new subscription (may differ from current)
     *   payment_method_id  string  required — Stripe pm_xxx
     *   pricing_id         int     optional — selected pricing tier
     *   offer_type         string  optional — selected offer/delivery type
     *
     * Workflow:
     *   1. Validate membership + subscription ownership.
     *   2. Delegate to SubscriptionRenewalService (charges Stripe, runs transaction).
     *   3. Return both old and new subscription records.
     */
    public function renewForMember(Request $request, int $memberId, int $subscriptionId): mixed
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $siteId = SiteContext::getId();

        // Ownership check before delegating to service
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $memberId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $planId = (int)$request->input('plan_id');
        $paymentMethodId = trim((string)$request->input('payment_method_id', ''));
        $pricingId = $request->input('pricing_id') ? (int)$request->input('pricing_id') : null;
        $offerType = trim((string)$request->input('offer_type', '')) ?: null;

        if (!$planId) {
            return $this->errorResponse('plan_id is required.', 422);
        }

        if ($paymentMethodId === '') {
            return $this->errorResponse('payment_method_id is required.', 422);
        }

        $agentId = (int)Auth::id();

        try {
            $result = $this->renewalService->renew(
                subscriptionId: $subscriptionId,
                planId: $planId,
                paymentMethodId: $paymentMethodId,
                amountPaid: null,
                agentId: $agentId,
                siteId: $siteId,
                pricingId: $pricingId,
                offerType: $offerType,
            );

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Subscription renewed successfully.',
                'old_subscription' => $result['old_subscription'],
                'new_subscription' => $result['new_subscription'],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Logger::error('Failed to renew subscription', [
                'subscription_id' => $subscriptionId,
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // NEW — Ticket 2: Subscription Product Switch
    // =========================================================================

    /**
     * GET /api/{site}/crm/members/{memberId}/subscriptions/{subscriptionId}/switch-preview
     *
     * Returns the pro-rated credit available if the member switches plans.
     * Used by the frontend to display the credit before the agent confirms.
     *
     * Query param: new_plan_id (int, required)
     */
    public function switchPreview(Request $request, int $memberId, int $subscriptionId): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $siteId = SiteContext::getId();

        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $memberId) {
            return $this->errorResponse('Subscription not found.', 404);
        }

        $newPlanId = (int)$request->input('new_plan_id');

        if (!$newPlanId) {
            return $this->errorResponse('new_plan_id is required.', 422);
        }

        $newPlan = $this->planRepository->find($newPlanId);

        if (!$newPlan || !$newPlan->is_active || $newPlan->site_id !== $siteId) {
            return $this->errorResponse('Plan not found.', 404);
        }

        $credit = $this->productSwitchService->calculateCarriedOverCredit($subscription);
        $fullPrice = (float)$newPlan->price;
        $balance = max(0, round($fullPrice - $credit, 2));

        return $this->resourceResponse([
            'success' => true,
            'carried_over_credit' => $credit,
            'new_plan_full_price' => $fullPrice,
            'amount_due_transfer' => $balance,
            'amount_due_fresh' => $fullPrice,
        ]);
    }

    /**
     * POST /api/{site}/crm/members/{memberId}/subscriptions/{subscriptionId}/switch
     *
     * Body:
     *   new_plan_id        int     required
     *   switch_mode        string  required — 'transfer' | 'fresh'
     *   payment_method_id  string  required
     *   amount             float   required — amount to charge (after any credit)
     *   carried_over_credit float  optional — 0 for Mode B (fresh)
     */
    public function switchProductForMember(Request $request, int $memberId, int $subscriptionId): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $siteId = SiteContext::getId();

        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $memberId) {
            return $this->errorResponse('Subscription not found.', 404);
        }

        $newPlanId = (int)$request->input('new_plan_id');
        $switchMode = trim((string)$request->input('switch_mode', ''));
        $paymentMethodId = trim((string)$request->input('payment_method_id', ''));
        $amount = (float)$request->input('amount', 0);
        $carriedOverCredit = (float)$request->input('carried_over_credit', 0);

        if (!$newPlanId) {
            return $this->errorResponse('new_plan_id is required.', 422);
        }

        if (!in_array($switchMode, ['transfer', 'fresh'], true)) {
            return $this->errorResponse("switch_mode must be 'transfer' or 'fresh'.", 422);
        }

        if ($paymentMethodId === '') {
            return $this->errorResponse('payment_method_id is required.', 422);
        }

        if ($switchMode === 'fresh' && $amount <= 0) {
            return $this->errorResponse('amount must be greater than zero for fresh switches.', 422);
        }

        $agentId = (int)Auth::id();

        try {
            $result = $this->productSwitchService->switch(
                subscriptionId: $subscriptionId,
                newPlanId: $newPlanId,
                switchMode: $switchMode,
                paymentMethodId: $paymentMethodId,
                amountToCharge: $amount,
                carriedOverCredit: $carriedOverCredit,
                agentId: $agentId,
                siteId: $siteId,
            );

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Subscription switched successfully.',
                'old_subscription' => $result['old_subscription'],
                'new_subscription' => $result['new_subscription'],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Logger::error('Failed to switch subscription product', [
                'subscription_id' => $subscriptionId,
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // NEW — Ticket 3: Issue Replacement
    // =========================================================================

    /**
     * POST /api/{site}/crm/members/{memberId}/subscriptions/{subscriptionId}/issues/{issueId}/replace
     *
     * Body:
     *   reason  string  required
     */
    public function requestIssueReplacement(
        Request $request,
        int     $memberId,
        int     $subscriptionId,
        int     $issueId,
    ): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $siteId = SiteContext::getId();

        // Verify subscription ownership before delegating to service
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $memberId) {
            return $this->errorResponse('Subscription not found.', 404);
        }

        $reason = trim((string)$request->input('reason', ''));

        if ($reason === '') {
            return $this->errorResponse('reason is required.', 422);
        }

        $agentId = (int)Auth::id();

        try {
            $replacement = $this->replacementService->requestReplacement(
                subscriptionId: $subscriptionId,
                issueId: $issueId,
                reason: $reason,
                agentId: $agentId,
                siteId: $siteId,
            );

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Issue replacement requested successfully.',
                'replacement' => $replacement,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            Logger::error('Failed to request issue replacement', [
                'subscription_id' => $subscriptionId,
                'issue_id' => $issueId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // NEW — Ticket 4: Suspend Subscription
    // =========================================================================

    /**
     * POST /api/{site}/crm/members/{memberId}/subscriptions/{subscriptionId}/suspend
     *
     * Body:
     *   reason  string  required
     */
    public function suspendForMember(Request $request, int $memberId, int $subscriptionId): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $siteId = SiteContext::getId();

        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $memberId) {
            return $this->errorResponse('Subscription not found.', 404);
        }

        $reason = trim((string)$request->input('reason', ''));
        $agentId = (int)Auth::id();

        if ($reason === '') {
            return $this->errorResponse('reason is required.', 422);
        }

        try {
            $suspended = $this->suspendAction->execute(
                subscriptionId: $subscriptionId,
                memberId: $memberId,
                agentId: $agentId,
                reason: $reason,
                siteId: $siteId,
            );

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Subscription suspended successfully.',
                'subscription' => $suspended,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            Logger::error('Failed to suspend subscription', [
                'subscription_id' => $subscriptionId,
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // Payments
    // =========================================================================

    /** GET /api/{site}/crm/members/{memberId}/payments */
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
                $subPayments = $this->paymentRepository->findByMemberSubscriptions($memberId, $siteId);
                $payments = $payments->merge($subPayments);
            }

            if (in_array($context, ['all', 'orders'], true)) {
                $orderPayments = $this->paymentRepository->findByMemberOrders($memberId);
                $payments = $payments->merge($orderPayments);
            }

            $sorted = $payments->unique('id')->sortByDesc('created_at')->values();
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
            Logger::error('Failed to fetch payments for member', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse(['success' => false, 'message' => 'Failed to load payments.'], 500);
        }
    }

    /**
     * POST /api/{site}/crm/members/{memberId}/payments/{paymentId}/refund
     *
     * Refund a single payment back to the customer via Stripe.
     *
     * Body:
     *   amount         float   optional — partial refund; omit for full refund
     *   reason         string  optional — reason code forwarded to Stripe
     *   internal_notes string  optional — stored in metadata only
     *   notify_customer bool   optional (default true)
     */
    public function refundPayment(Request $request, int $memberId, int $paymentId): mixed
    {
        if (!Auth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $siteId = SiteContext::getId();

        $payment = $this->paymentRepository->find($paymentId);

        if (!$payment || $payment->site_id !== $siteId) {
            return $this->resourceResponse(['success' => false, 'message' => 'Payment not found.'], 404);
        }

        // Verify the payment belongs to this member (via subscription or order)
        if (!$this->paymentBelongsToMember($payment, $memberId)) {
            return $this->resourceResponse(['success' => false, 'message' => 'Payment not found.'], 404);
        }

        $subscription = $payment->subscription_id
            ? $this->subscriptionRepository->find((int)$payment->subscription_id)
            : null;

        if (!$subscription) {
            return $this->resourceResponse(['success' => false, 'message' => 'Subscription payment not found.'], 404);
        }

        if ($payment->status === 'refunded') {
            return $this->resourceResponse(['success' => false, 'message' => 'Payment has already been refunded.'], 422);
        }

        if (!in_array($payment->status, ['completed', 'paid'], true)) {
            return $this->resourceResponse(['success' => false, 'message' => 'Only completed payments can be refunded.'], 422);
        }

        $rawAmount = $request->input('amount');
        $refundAmount = $rawAmount !== null ? (float)$rawAmount : (float)$payment->amount;

        if ($refundAmount <= 0) {
            return $this->resourceResponse(['success' => false, 'message' => 'Refund amount must be greater than zero.'], 422);
        }

        if ($refundAmount > (float)$payment->amount) {
            return $this->resourceResponse(['success' => false, 'message' => 'Refund amount cannot exceed the original payment.'], 422);
        }

        $reason         = trim((string)$request->input('reason', 'customer_request'));
        $internalNotes  = trim((string)$request->input('internal_notes', ''));
        $notifyCustomer = (bool)$request->input('notify_customer', true);

        try {
            $result = $this->refundService->executeWithStrategy(
                $subscription,
                $this->refundStrategyForPayment($payment, $refundAmount, $reason, [
                    'internal_notes'  => $internalNotes,
                    'notify_customer' => $notifyCustomer,
                    'refunded_by'     => Auth::id(),
                ])
            );

            $refundPayment = $result['refund_payment'];

            $this->paymentRepository->update($payment->id, ['status' => 'refunded']);

            Logger::info('CRM payment refund processed', [
                'payment_id'        => $payment->id,
                'refund_payment_id' => $refundPayment->id,
                'amount'            => $refundAmount,
                'member_id'         => $memberId,
                'agent_id'          => Auth::id(),
            ]);

            return $this->resourceResponse([
                'success'        => true,
                'message'        => 'Refund processed successfully.',
                'refund_payment' => $refundPayment,
                'amount'         => $result['amount'],
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to process payment refund', [
                'payment_id' => $paymentId,
                'member_id'  => $memberId,
                'error'      => $e->getMessage(),
            ]);

            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/{site}/crm/members/{memberId}/payments/bulk-refund
     *
     * Refund multiple payments in one request.
     *
     * Body:
     *   payment_ids    int[]   required — IDs to refund
     *   reason         string  optional
     *   internal_notes string  optional
     *   notify_customer bool   optional (default true)
     *
     * Returns per-payment results so the caller can surface partial failures.
     */
    public function bulkRefundPayments(Request $request, int $memberId): mixed
    {
        if (!Auth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $siteId = SiteContext::getId();

        $paymentIds = $request->input('payment_ids', []);

        if (!is_array($paymentIds) || count($paymentIds) === 0) {
            return $this->resourceResponse(['success' => false, 'message' => 'payment_ids must be a non-empty array.'], 422);
        }

        if (count($paymentIds) > 50) {
            return $this->resourceResponse(['success' => false, 'message' => 'Cannot bulk refund more than 50 payments at once.'], 422);
        }

        $reason         = trim((string)$request->input('reason', 'customer_request'));
        $internalNotes  = trim((string)$request->input('internal_notes', ''));
        $notifyCustomer = (bool)$request->input('notify_customer', true);

        $results   = [];
        $succeeded = 0;
        $failed    = 0;

        foreach ($paymentIds as $paymentId) {
            $paymentId = (int)$paymentId;

            try {
                $payment = $this->paymentRepository->find($paymentId);

                if (!$payment || $payment->site_id !== $siteId || !$this->paymentBelongsToMember($payment, $memberId)) {
                    $results[] = ['payment_id' => $paymentId, 'success' => false, 'message' => 'Payment not found.'];
                    $failed++;
                    continue;
                }

                if ($payment->status === 'refunded') {
                    $results[] = ['payment_id' => $paymentId, 'success' => false, 'message' => 'Already refunded.'];
                    $failed++;
                    continue;
                }

                if (!in_array($payment->status, ['completed', 'paid'], true)) {
                    $results[] = ['payment_id' => $paymentId, 'success' => false, 'message' => 'Payment is not refundable (status: ' . $payment->status . ').'];
                    $failed++;
                    continue;
                }

                $subscription = $payment->subscription_id
                    ? $this->subscriptionRepository->find((int)$payment->subscription_id)
                    : null;

                if (!$subscription) {
                    $results[] = ['payment_id' => $paymentId, 'success' => false, 'message' => 'Subscription payment not found.'];
                    $failed++;
                    continue;
                }

                $refundAmount = (float)$payment->amount;

                $result = $this->refundService->executeWithStrategy(
                    $subscription,
                    $this->refundStrategyForPayment($payment, $refundAmount, $reason, [
                        'internal_notes'  => $internalNotes,
                        'notify_customer' => $notifyCustomer,
                        'refunded_by'     => Auth::id(),
                        'bulk_refund'     => true,
                    ])
                );

                $refundPayment = $result['refund_payment'];

                $this->paymentRepository->update($payment->id, ['status' => 'refunded']);

                $results[] = [
                    'payment_id'     => $paymentId,
                    'success'        => true,
                    'amount'         => $result['amount'],
                    'refund_payment' => $refundPayment,
                ];

                $succeeded++;
            } catch (\Exception $e) {
                Logger::error('Bulk refund failed for payment', [
                    'payment_id' => $paymentId,
                    'member_id'  => $memberId,
                    'error'      => $e->getMessage(),
                ]);

                $results[] = ['payment_id' => $paymentId, 'success' => false, 'message' => $e->getMessage()];
                $failed++;
            }
        }

        Logger::info('CRM bulk refund completed', [
            'member_id' => $memberId,
            'agent_id'  => Auth::id(),
            'succeeded' => $succeeded,
            'failed'    => $failed,
        ]);

        return $this->resourceResponse([
            'success'   => $failed === 0,
            'message'   => "{$succeeded} refund(s) processed" . ($failed > 0 ? ", {$failed} failed." : '.'),
            'results'   => $results,
            'succeeded' => $succeeded,
            'failed'    => $failed,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function paymentBelongsToMember(mixed $payment, int $memberId): bool
    {
        // Check via subscription
        if ($payment->subscription_id) {
            $sub = $this->subscriptionRepository->find($payment->subscription_id);
            if ($sub && $sub->member_id === $memberId) {
                return true;
            }
        }

        // Check via order
        if ($payment->order_id) {
            $order = $this->orderRepository->find($payment->order_id);
            if ($order && (int)$order->user_id === $memberId) {
                return true;
            }
        }

        return false;
    }

    private function refundStrategyForPayment(mixed $payment, float $amount, string $reason, array $metadata = []): RefundStrategy
    {
        return new class($payment, $amount, $reason, $metadata) implements RefundStrategy {
            public function __construct(
                private readonly mixed $payment,
                private readonly float $amount,
                private readonly string $reason,
                private readonly array $metadata,
            ) {
            }

            public function calculate(\App\Models\Subscription $subscription): RefundResult
            {
                return new RefundResult(
                    amount: $this->amount,
                    type: $this->amount < (float)$this->payment->amount ? 'manual' : 'full',
                    meta: array_merge($this->metadata, [
                        'original_payment_id' => $this->payment->id,
                        'original_amount'     => $this->payment->amount,
                        'transaction_id'      => $this->payment->transaction_id,
                        'payment_method'      => $this->payment->payment_method,
                        'payment_provider'    => $this->payment->payment_provider,
                        'reason'              => $this->reason,
                    ]),
                );
            }
        };
    }

    /** GET /api/{site}/crm/subscriptions/plans/{planId} */
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

        return $this->resourceResponse(['success' => true, 'plan' => $plan->toArray()]);
    }

    /** GET /api/{site}/crm/members/{memberId}/subscription-stats */
    public function subscriptionStatsForMember(Request $request, int $memberId): mixed
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $siteId = SiteContext::getId();
        $subscriptions = $this->subscriptionRepository->getSubscriptionHistory($memberId, $siteId);

        $activeCount = $subscriptions->filter(fn($s) => in_array($s->status, ['active', 'trialing']))->count();
        $cancelledCount = $subscriptions->filter(fn($s) => $s->status === 'cancelled')->count();

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

    /** GET /api/{site}/crm/members/{memberId}/orders */
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
            Logger::error('Failed to fetch orders for member', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse(['success' => false, 'message' => 'Failed to load orders.'], 500);
        }
    }

    /** GET /api/{site}/crm/members/{memberId}/activity */
    public function activityForMember(Request $request, int $memberId): mixed
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $siteId = SiteContext::getId();
        $page = max(1, (int)$request->input('page', 1));
        $perPage = min(50, max(1, (int)$request->input('per_page', 15)));

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
