<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Billing\OrderRepository;
use App\Services\Billing\Order\OrderManager;
use App\Services\Subscriptions\SubscriptionListingService;

/**
 * ShopAccountController
 *
 * Read-only account dashboard views.
 * All mutations go through ShopAccountApiController (JSON responses).
 *
 * Routes:
 *   GET /account                  → overview()
 *   GET /account/subscriptions    → subscriptions()
 *   GET /account/orders           → orders()
 *   GET /account/orders/{id}      → orderDetail()
 *   GET /account/billing          → billing()
 */
class ShopAccountController extends Controller
{
    public function __construct(
        private readonly SubscriptionListingService $subscriptionListingService,
        private readonly OrderManager               $orderManager,
        private readonly OrderRepository            $orderRepository,
    )
    {
        parent::__construct();
    }

    public function overview(Request $request): mixed
    {
        if (!MemberAuth::check()) {
            return $this->errorResponse('Unauthorized');
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $grouped = $this->subscriptionListingService->getGroupedSubscriptions($member->id, $siteId);
        $summary = $this->subscriptionListingService->getSubscriptionSummary($member->id, $siteId);
        $recentOrders = $this->orderManager->getByUser($member->id, 5);

        $activeSubscriptions = array_slice(
            array_merge($grouped['active']['print'] ?? [], $grouped['active']['digital'] ?? []),
            0,
            3
        );

        return $this->view('subscriptions/account/overview', [
            'member' => $member,
            'subscription_summary' => $summary,
            'active_subscriptions' => $activeSubscriptions,
            'recent_orders' => $recentOrders,
            'active_tab' => 'overview',
        ]);
    }

    public function subscriptions(Request $request): mixed
    {
        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $grouped = $this->subscriptionListingService->getGroupedSubscriptions($member->id, $siteId);
        $summary = $this->subscriptionListingService->getSubscriptionSummary($member->id, $siteId);

        // Annotate each subscription with server-computed eligibility flags.
        // The view reads these — JS modals use them to decide what to show.
        $grouped = $this->annotateSubscriptionEligibility($grouped, $member->id);

        return $this->view('subscriptions/account/subscriptions', [
            'member' => $member,
            'grouped' => $grouped,
            'summary' => $summary,
            'active_tab' => 'subscriptions',
        ]);
    }

    private function annotateSubscriptionEligibility(array $grouped, int $memberId): array
    {
        foreach (['active', 'expired'] as $statusGroup) {
            foreach (['print', 'digital'] as $type) {
                if (empty($grouped[$statusGroup][$type])) {
                    continue;
                }
                $grouped[$statusGroup][$type] = array_map(
                    function (array $sub) use ($memberId) {
                        // $sub['can_pause']  = $this->subscriptionPauseService->canPause($sub['id'], $memberId);
                        //$sub['can_resume'] = $this->subscriptionPauseService->canResume($sub['id'], $memberId);
                        // can_cancel is derived from status — active and not terminal
                        $sub['can_cancel'] = in_array($sub['status'] ?? '', ['active', 'paused'], true);
                        return $sub;
                    },
                    $grouped[$statusGroup][$type]
                );
            }
        }
        return $grouped;
    }

    public function orders(Request $request): mixed
    {
        $member = MemberAuth::getMember();
        $page = max(1, (int)$request->input('page', 1));
        $perPage = 10;

        $filters = [
            'search' => trim($request->input('search', '')),
            'date_from' => trim($request->input('date_from', '')),
            'date_to' => trim($request->input('date_to', '')),
            'status' => trim($request->input('status', '')),
        ];

        $result = $this->orderManager->getByUserPaginated($member->id, $page, $perPage, $filters);

        $orders = $result['data']->map(function ($order) {
            $order->can_cancel = $order->canBeCancelled();
            return $order;
        });

        return $this->view('subscriptions/account/orders', [
            'member' => $member,
            'orders' => $orders,
            'pagination' => $result['pagination'],
            'filters' => $filters,
            'active_tab' => 'orders',
        ]);
    }

    public function orderDetail(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();
        $order = $this->orderManager->findById($id);

        if (!$order || (int)$order->user_id !== $member->id) {
            return $this->redirect('/account/orders');
        }

        $order->can_cancel = $order->canBeCancelled();

        return $this->view('subscriptions/account/order-detail', [
            'member' => $member,
            'order' => $order,
            'active_tab' => 'orders',
        ]);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    public function billing(Request $request): mixed
    {
        $member = MemberAuth::getMember();

        return $this->view('subscriptions/account/billing', [
            'member' => $member,
            'active_tab' => 'billing',
        ]);
    }
}