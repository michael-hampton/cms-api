<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Enums\Subscriptions\SubscriptionCancellationReason;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Repositories\Billing\OrderRepository;
use App\Services\Billing\Order\OrderManager;
use App\Services\Subscriptions\SubscriptionListingService;
use App\Services\Subscriptions\SubscriptionAccountFaqProvider;

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
        private readonly SubscriptionAccountFaqProvider $faqProvider,
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
        $grouped = $this->subscriptionListingService->getGroupedSubscriptions($member->id);
        $summary = $this->subscriptionListingService->getSubscriptionSummary($member->id);
        $recentOrders = $this->orderManager->getByUser($member->id, 5);

        $activeSubscriptions = array_slice($grouped['current'] ?? [], 0, 3);

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
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $grouped = $this->subscriptionListingService->getGroupedSubscriptions($member->id);
        $summary = $this->subscriptionListingService->getSubscriptionSummary($member->id);

        return $this->view('subscriptions/account/subscriptions', [
            'member' => $member,
            'grouped' => $grouped,
            'summary' => $summary,
            'cancellation_reasons' => array_map(
                static fn(SubscriptionCancellationReason $reason): array => [
                    'value' => $reason->value,
                    'label' => $reason->label(),
                ],
                SubscriptionCancellationReason::cases()
            ),
            'faqs' => $this->faqProvider->all(),
            'active_tab' => 'subscriptions',
        ]);
    }

    public function orders(Request $request): mixed
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

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
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

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
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();

        return $this->view('subscriptions/account/billing', [
            'member' => $member,
            'active_tab' => 'billing',
        ]);
    }

    public function renew(int $id, Request $request): mixed
    {
        $subscription = $this->ownedSubscription($id);

        if (!$subscription || !$subscription->plan_id
            || !$this->hasContinuationAction($subscription, 'renew')) {
            return $this->redirect('/press-stack/account/subscriptions');
        }

        return $this->redirect('/checkout?subscription_id=' . $subscription->id . '&renewal=true');
    }

    public function resubscribe(int $id, Request $request): mixed
    {
        $subscription = $this->ownedSubscription($id);

        if (!$subscription || !$subscription->plan_id
            || !$this->hasContinuationAction($subscription, 'resubscribe')) {
            return $this->redirect('/press-stack');
        }

        return $this->redirect('/checkout?subscription_id=' . $subscription->id . '&resubscribe=true');
    }

    private function ownedSubscription(int $id): ?\App\Models\Subscription
    {
        $member = MemberAuth::getMember();
        $subscription = \App\Models\Subscription::find($id);

        if (!$member || !$subscription
            || (int)$subscription->member_id !== (int)$member->id) {
            return null;
        }

        return $subscription;
    }

    private function hasContinuationAction(\App\Models\Subscription $subscription, string $key): bool
    {
        $formatted = $this->subscriptionListingService->formatSubscriptionForListing($subscription);

        foreach ($formatted['actions'] as $action) {
            if (($action['key'] ?? null) === $key) {
                return true;
            }
        }

        return false;
    }
}
