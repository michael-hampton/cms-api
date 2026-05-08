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
use App\Models\IssueDelivery;
use App\Repositories\Billing\PaymentRepository;
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

        $result = $this->cancellationService->cancelSubscription($subscriptionId, [
            'cancel_at_period_end' => $cancelAtPeriodEnd,
            'reason' => $reason ?: null,
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
     *   filter  string  all|upcoming|previous|missed  (default: all)
     *   from    string  Y-m-d  optional date range start
     *   to      string  Y-m-d  optional date range end
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
        $now = new \DateTime();

        $from = $fromRaw ? new \DateTime($fromRaw) : null;
        $to = $toRaw ? (new \DateTime($toRaw))->setTime(23, 59, 59) : null;

        try {
            // ── Scheduled deliveries (IssueDelivery) ───────────────────────
            $schedules = IssueDelivery::where('subscription_plan_id', $subscription->plan_id)->get();

            // ── Delivered records (IssuesDelivered) ────────────────────────
            $delivered = $this->issuesDeliveredRepository->getForSubscription($subscriptionId);
            $deliveredByScheduleId = [];
            foreach ($delivered as $d) {
                $deliveredByScheduleId[$d->issue_delivery_id] = $d;
            }

            $rows = [];

            foreach ($schedules as $schedule) {
                $estimatedDate = $schedule->estimated_delivery_date
                    ? $schedule->estimated_delivery_date
                    : null;
                $onSaleDate = $schedule->on_sale_date
                    ? $schedule->on_sale_date
                    : null;

                // Classify row type
                $deliveredRecord = $deliveredByScheduleId[$schedule->id] ?? null;

                if ($deliveredRecord && $deliveredRecord->isDelivered()) {
                    $type = 'delivered';
                } elseif ($estimatedDate && $estimatedDate < $now && !$deliveredRecord) {
                    $type = 'missed';
                } else {
                    $type = 'upcoming';
                }

                // Apply filter
                if ($filter === 'upcoming' && $type !== 'upcoming') continue;
                if ($filter === 'previous' && $type !== 'delivered') continue;
                if ($filter === 'missed' && $type !== 'missed') continue;

                // Apply date range filter on estimated_delivery_date
                if ($from && $estimatedDate && $estimatedDate < $from) continue;
                if ($to && $estimatedDate && $estimatedDate > $to) continue;

                $rows[] = [
                    'id' => $schedule->id,
                    'issue_number' => $schedule->issue_number,
                    'issue_title' => $schedule->issue_title,
                    'on_sale_date' => $onSaleDate?->format('Y-m-d'),
                    'estimated_delivery_date' => $estimatedDate?->format('Y-m-d'),
                    'status' => $schedule->status,
                    'type' => $type,
                    'delivered_at' => $deliveredRecord?->delivered_at?->format('Y-m-d H:i:s'),
                ];
            }

            // Sort: upcoming asc by estimated date, delivered/missed desc
            usort($rows, function (array $a, array $b) {
                $dateA = $a['estimated_delivery_date'] ?? '';
                $dateB = $b['estimated_delivery_date'] ?? '';
                if ($a['type'] === 'upcoming' && $b['type'] === 'upcoming') {
                    return strcmp($dateA, $dateB);
                }
                return strcmp($dateB, $dateA);
            });

            return $this->resourceResponse([
                'success' => true,
                'issues' => $rows,
                'total' => count($rows),
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
    // Payments
    // =========================================================================

    /**
     * GET /api/{site}/admin/members/{memberId}/payments
     *
     * Query params:
     *   context  string  all|subscription|orders  (default: all)
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

        try {
            // Resolve all subscription IDs belonging to this member
            $subscriptions = $this->subscriptionRepository->getSubscriptionHistory($memberId, $siteId);
            $subscriptionIds = $subscriptions->pluck('id')->all();

            $payments = collect();

            // ── Subscription payments ──────────────────────────────────────
            if (in_array($context, ['all', 'subscription'], true)) {
                foreach ($subscriptionIds as $subId) {
                    $subPayments = $this->paymentRepository->findBySubscriptionId($subId);
                    $payments = $payments->merge($subPayments);
                }
            }

            // ── Order payments ─────────────────────────────────────────────
            if (in_array($context, ['all', 'orders'], true)) {
                $member = $this->memberRepository->find($memberId);

                if ($member) {
                    // Retrieve orders for member and fetch their payments
                    $orders = $member->orders ?? collect();
                    foreach ($orders as $order) {
                        $orderPayments = $this->paymentRepository->findByOrderId($order->id);
                        // Exclude payments already captured via subscription_id to avoid duplication
                        foreach ($orderPayments as $p) {
                            if (!$p->subscription_id) {
                                $payments->push($p);
                            }
                        }
                    }
                }
            }

            // Deduplicate by ID and sort newest first
            $unique = $payments
                ->unique('id')
                ->sortByDesc('created_at')
                ->values();

            $rows = $unique->map(fn($p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'currency' => $p->currency ?? 'GBP',
                'status' => $p->status,
                'payment_method' => $p->payment_method,
                'paid_at' => $p->paid_at?->format('Y-m-d H:i:s'),
                'created_at' => $p->created_at->format('Y-m-d H:i:s'),
                'order_id' => $p->order_id,
                'subscription_id' => $p->subscription_id,
            ])->all();

            return $this->resourceResponse([
                'success' => true,
                'payments' => $rows,
                'total' => count($rows),
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
}