<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Authorization\AuthenticationService;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Country;
use App\Models\Member;
use App\Models\Order;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Subscriptions\SubscriptionAccountModalPlanRepository;
use App\Services\Billing\Order\OrderManager;
use App\Services\Subscriptions\SubscriptionAccountContext;
use App\Services\Subscriptions\SubscriptionAccountPageProvider;
use App\Services\Subscriptions\SubscriptionListingService;

class ShopAccountController extends Controller
{
    public function __construct(
        private readonly SubscriptionListingService $subscriptionListingService,
        private readonly SubscriptionAccountPageProvider $subscriptionAccountPageProvider,
        private readonly OrderManager $orderManager,
        private readonly OrderRepository $orderRepository,
        private readonly SubscriptionAccountModalPlanRepository $modalPlanRepository,
        private readonly AuthenticationService $authenticationService,
    ) {
        parent::__construct();
    }

    public function overview(Request $request): mixed
    {
        if (!MemberAuth::check()) {
            return $this->guestAccountPage('overview');
        }

        $member = MemberAuth::getMember();
        $grouped = $this->subscriptionListingService->getGroupedSubscriptions($member->id, null);
        $summary = $this->subscriptionListingService->getSubscriptionSummary($member->id, null);
        $recentOrders = $this->orderManager->getByUser($member->id, 5);

        return $this->view('subscriptions/account/overview', [
            'member' => $member,
            'subscription_summary' => $summary,
            'active_subscriptions' => array_slice($grouped['current'] ?? [], 0, 3),
            'recent_orders' => $recentOrders,
            'active_tab' => 'overview',
        ]);
    }

    public function subscriptions(Request $request): mixed
    {
        if (!MemberAuth::check()) {
            return $this->guestAccountPage('subscriptions');
        }

        $member = MemberAuth::getMember();
        $pageData = $this->subscriptionAccountPageProvider->forMember(
            $member->id,
            null,
            SubscriptionAccountContext::pressStack(),
        );

        $plans = $this->plansForAccountModal($pageData['grouped'] ?? []);
        $pageData['member'] = $member;
        $pageData['active_tab'] = 'subscriptions';
        $pageData['plans'] = $plans;
        $pageData['has_billing_history'] = $this->hasSubscriptionBillingHistory($member->id);
        $pageData['subscription_modal_data'] = [
            'plans' => $plans,
            'member' => $member,
            'show_modal' => false,
            'is_direct' => true,
        ];
        $pageData['account_context']['can_acquire_subscription'] = true;
        $pageData['account_context']['show_subscription_modal'] = true;

        return $this->view('subscriptions/account/subscriptions', $pageData);
    }

    public function paymentMethods(Request $request): mixed
    {
        if (!MemberAuth::check()) {
            return $this->guestAccountPage('payment_methods');
        }

        $member = MemberAuth::getMember();

        return $this->view('subscriptions/account/billing', [
            'member' => $member,
            'active_tab' => 'payment_methods',
            'billing_section' => 'payment_methods',
            'page_title' => 'Payment methods',
            'has_billing_history' => $this->hasSubscriptionBillingHistory($member->id),
            'countries' => Country::forDropdown(),
        ]);
    }

    public function manageAddresses(Request $request): mixed
    {
        if (!MemberAuth::check()) {
            return $this->guestAccountPage('addresses');
        }

        $member = MemberAuth::getMember();

        return $this->view('subscriptions/account/billing', [
            'member' => $member,
            'active_tab' => 'addresses',
            'billing_section' => 'addresses',
            'page_title' => 'Manage Addresses',
            'has_billing_history' => $this->hasSubscriptionBillingHistory($member->id),
            'countries' => Country::forDropdown(),
        ]);
    }

    public function faqs(Request $request): mixed
    {
        if (!MemberAuth::check()) {
            return $this->guestAccountPage('faqs');
        }

        $member = MemberAuth::getMember();
        $pageData = $this->subscriptionAccountPageProvider->forMember(
            $member->id,
            null,
            SubscriptionAccountContext::pressStack(),
        );

        $pageData['member'] = $member;
        $pageData['active_tab'] = 'faqs';
        $pageData['page_title'] = 'FAQs';
        $pageData['has_billing_history'] = $this->hasSubscriptionBillingHistory($member->id);

        return $this->view('subscriptions/account/faqs', $pageData);
    }

    public function billingHistory(Request $request): mixed
    {
        if (!MemberAuth::check()) {
            return $this->guestAccountPage('billing_history');
        }

        $member = MemberAuth::getMember();
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 10;
        $filters = [
            'search' => trim($request->input('search', '')),
            'date_from' => trim($request->input('date_from', '')),
            'date_to' => trim($request->input('date_to', '')),
            'status' => trim($request->input('status', '')),
        ];

        $allRows = $this->subscriptionBillingHistoryRows($member->id);
        $filteredRows = $this->filterBillingHistoryRows($allRows, $filters);
        $total = count($filteredRows);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $billingHistoryRows = array_slice($filteredRows, ($page - 1) * $perPage, $perPage);

        return $this->view('subscriptions/account/billing-history', [
            'member' => $member,
            'active_tab' => 'billing_history',
            'page_title' => 'Billing history',
            'billing_history_rows' => $billingHistoryRows,
            'billing_history_pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total' => $total,
                'per_page' => $perPage,
            ],
            'filters' => $filters,
            'has_billing_history' => !empty($allRows),
        ]);
    }

    public function orders(Request $request): mixed
    {
        if (!MemberAuth::check()) {
            return $this->guestAccountPage('orders');
        }

        $member = MemberAuth::getMember();
        $filters = [
            'status' => $request->input('status'),
            'search' => $request->input('search'),
        ];
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 10;
        $orders = $this->orderRepository->getOrdersForUser($member->id, $filters, $page, $perPage);

        return $this->view('subscriptions/account/orders', [
            'member' => $member,
            'orders' => $orders['items'] ?? [],
            'pagination' => $orders['pagination'] ?? [],
            'filters' => $filters,
            'active_tab' => 'orders',
        ]);
    }
}
