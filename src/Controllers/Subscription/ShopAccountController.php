<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Authorization\AuthenticationService;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
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
        $page = max(1, (int) $request->input('page', 1));
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
            'has_billing_history' => $this->hasSubscriptionBillingHistory($member->id),
        ]);
    }

    public function orderDetail(int $id, Request $request): mixed
    {
        if (!MemberAuth::check()) {
            return $this->guestAccountPage('orders');
        }

        $member = MemberAuth::getMember();
        $order = $this->orderManager->findById($id);

        if (!$order || (int) $order->user_id !== $member->id) {
            return $this->redirect('/account/orders');
        }

        $order->can_cancel = $order->canBeCancelled();

        return $this->view('subscriptions/account/order-detail', [
            'member' => $member,
            'order' => $order,
            'active_tab' => 'orders',
            'has_billing_history' => $this->hasSubscriptionBillingHistory($member->id),
        ]);
    }

    public function billing(Request $request): mixed
    {
        return $this->paymentMethods($request);
    }

    public function loginWithEmail(Request $request): mixed
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $redirect = $this->normaliseAccountRedirect((string) $request->input('redirect', '/press-stack/account'));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->failedAccountLogin($request, $redirect, 'Please enter a valid email address.');
        }

        $member = Member::findByEmail($email, null);

        if (!$member || !$member->isActive()) {
            return $this->failedAccountLogin($request, $redirect, 'We could not find an active account for that email address.');
        }

        MemberAuth::login($member);

        $siteId = (int) ($member->site_id ?: SiteContext::getId());
        $token = $this->authenticationService->createMemberToken($member, $siteId);
        $this->setMemberTokenCookie($token);

        if ($this->expectsJson($request)) {
            return $this->resourceResponse([
                'success' => true,
                'redirect' => $redirect,
                'member' => [
                    'id' => $member->id,
                    'email' => $member->email,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'display_name' => $member->display_name,
                ],
            ]);
        }

        return $this->redirect($redirect);
    }

    public function renew(int $id, Request $request): mixed
    {
        $subscription = $this->ownedSubscription($id);

        if (!$subscription || !$subscription->plan_id || !$this->hasContinuationAction($subscription, 'renew')) {
            return $this->redirect('/press-stack/account/subscriptions');
        }

        return $this->redirect('/checkout?subscription_id=' . $subscription->id . '&renewal=true');
    }

    public function resubscribe(int $id, Request $request): mixed
    {
        return $this->redirect('/press-stack/account/subscriptions');
    }

    private function subscriptionBillingHistoryRows(int $memberId): array
    {
        $rows = [];
        $orders = Order::with(['payments'])
            ->where('user_id', $memberId)
            ->whereNotNull('one_time_subscription_id')
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($orders as $order) {
            $payments = $order->payments ?? [];
            $hasPaymentRows = false;
            $orderId = (int) $order->id;
            $orderDate = $order->created_at ?? null;

            foreach ($payments as $payment) {
                $hasPaymentRows = true;
                $dateValue = $payment->paid_at ?? $payment->received_at ?? $payment->created_at ?? $orderDate;

                $rows[] = [
                    'date' => $this->formatBillingDate($dateValue),
                    'date_value' => $this->formatBillingDateValue($dateValue),
                    'reference' => $payment->reference
                        ?? $payment->stripe_invoice_id
                        ?? $payment->transaction_id
                        ?? $payment->payment_intent_id
                        ?? $order->order_number
                        ?? '—',
                    'order_id' => $orderId,
                    'order_url' => '/press-stack/account/orders/' . $orderId,
                    'order_number' => $order->order_number ?? $orderId,
                    'subscription_id' => $order->one_time_subscription_id,
                    'order_status' => $order->status ?? 'pending',
                    'payment_status' => $payment->status ?? $order->payment_status ?? 'pending',
                    'amount' => $this->formatBillingAmount($payment->amount ?? $order->total ?? 0, $payment->currency ?? $order->currency ?? ''),
                    'invoice_url' => $payment->hosted_invoice_url ?? null,
                ];
            }

            if (!$hasPaymentRows) {
                $dateValue = $order->completed_at ?? $orderDate;

                $rows[] = [
                    'date' => $this->formatBillingDate($dateValue),
                    'date_value' => $this->formatBillingDateValue($dateValue),
                    'reference' => $order->payment_intent_id ?? $order->order_number ?? '—',
                    'order_id' => $orderId,
                    'order_url' => '/press-stack/account/orders/' . $orderId,
                    'order_number' => $order->order_number ?? $orderId,
                    'subscription_id' => $order->one_time_subscription_id,
                    'order_status' => $order->status ?? 'pending',
                    'payment_status' => $order->payment_status ?? 'pending',
                    'amount' => $this->formatBillingAmount($order->total ?? 0, $order->currency ?? ''),
                    'invoice_url' => null,
                ];
            }
        }

        return $rows;
    }

    private function filterBillingHistoryRows(array $rows, array $filters): array
    {
        $search = strtolower((string) ($filters['search'] ?? ''));
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');
        $status = strtolower((string) ($filters['status'] ?? ''));

        return array_values(array_filter($rows, function (array $row) use ($search, $dateFrom, $dateTo, $status): bool {
            if ($search !== '') {
                $haystack = strtolower(implode(' ', [
                    $row['order_number'] ?? '',
                    $row['reference'] ?? '',
                    $row['subscription_id'] ?? '',
                ]));

                if (!str_contains($haystack, $search)) {
                    return false;
                }
            }

            $rowDate = (string) ($row['date_value'] ?? '');

            if ($dateFrom !== '' && ($rowDate === '' || $rowDate < $dateFrom)) {
                return false;
            }

            if ($dateTo !== '' && ($rowDate === '' || $rowDate > $dateTo)) {
                return false;
            }

            if ($status !== '') {
                $paymentStatus = strtolower((string) ($row['payment_status'] ?? ''));
                $orderStatus = strtolower((string) ($row['order_status'] ?? ''));

                if ($paymentStatus !== $status && $orderStatus !== $status) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function hasSubscriptionBillingHistory(int $memberId): bool
    {
        return Order::where('user_id', $memberId)
            ->whereNotNull('one_time_subscription_id')
            ->count() > 0;
    }

    private function formatBillingDate(mixed $value): string
    {
        if (!$value) {
            return '—';
        }

        if (is_object($value) && method_exists($value, 'format')) {
            return $value->format('j M Y');
        }

        $timestamp = strtotime((string) $value);

        return $timestamp ? date('j M Y', $timestamp) : (string) $value;
    }

    private function formatBillingDateValue(mixed $value): string
    {
        if (!$value) {
            return '';
        }

        if (is_object($value) && method_exists($value, 'format')) {
            return $value->format('Y-m-d');
        }

        $timestamp = strtotime((string) $value);

        return $timestamp ? date('Y-m-d', $timestamp) : '';
    }

    private function formatBillingAmount(float|int|string|null $amount, ?string $currency): string
    {
        $code = strtoupper((string) ($currency ?: ''));
        $value = is_numeric($amount) ? (float) $amount : 0.0;

        return trim($code . ' ' . number_format($value, 2));
    }

    private function guestAccountPage(string $activeTab, ?string $error = null): mixed
    {
        return $this->view('subscriptions/account/guest', [
            'active_tab' => $activeTab,
            'page_title' => $this->accountPageTitle($activeTab),
            'account_login_required' => true,
            'account_login_error' => $error ?? $this->loginErrorFromQuery(),
            'account_login_redirect' => $this->currentAccountUrl(),
        ]);
    }

    private function failedAccountLogin(Request $request, string $redirect, string $message): mixed
    {
        if ($this->expectsJson($request)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        $separator = str_contains($redirect, '?') ? '&' : '?';

        return $this->redirect($redirect . $separator . 'account_login_error=' . urlencode($message));
    }

    private function expectsJson(Request $request): bool
    {
        return $request->getHeader('X-Requested-With') === 'XMLHttpRequest'
            || str_contains((string) $request->getHeader('Content-Type'), 'application/json')
            || str_contains((string) $request->getHeader('Accept'), 'application/json');
    }

    private function normaliseAccountRedirect(string $redirect): string
    {
        $path = parse_url($redirect, PHP_URL_PATH) ?: '/press-stack/account';
        $query = parse_url($redirect, PHP_URL_QUERY);

        if ($path !== '/press-stack/account' && !str_starts_with($path, '/press-stack/account/')) {
            return '/press-stack/account';
        }

        return $query ? $path . '?' . $query : $path;
    }

    private function currentAccountUrl(): string
    {
        return $this->normaliseAccountRedirect($_SERVER['REQUEST_URI'] ?? '/press-stack/account');
    }

    private function loginErrorFromQuery(): ?string
    {
        $message = $_GET['account_login_error'] ?? null;

        return is_string($message) && $message !== '' ? $message : null;
    }

    private function accountPageTitle(string $activeTab): string
    {
        return match ($activeTab) {
            'subscriptions' => 'My Subscriptions',
            'payment_methods' => 'Payment methods',
            'addresses' => 'Manage Addresses',
            'faqs' => 'FAQs',
            'billing_history' => 'Billing history',
            'orders' => 'Orders',
            'billing' => 'Billing',
            default => 'Overview',
        };
    }

    private function setMemberTokenCookie(string $token): void
    {
        setcookie('member_access_token', $token, [
            'expires' => time() + (8 * 60 * 60),
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function ownedSubscription(int $id): ?\App\Models\Subscription
    {
        $member = MemberAuth::getMember();
        $subscription = \App\Models\Subscription::find($id);

        if (!$member || !$subscription || (int) $subscription->member_id !== (int) $member->id) {
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

    private function plansForAccountModal(array $grouped): iterable
    {
        $siteIds = [];
        $resubscribePlanIds = [];

        foreach (['current', 'action_required', 'previous'] as $group) {
            foreach ($grouped[$group] ?? [] as $subscription) {
                if (!empty($subscription['site_id'])) {
                    $siteIds[] = (int) $subscription['site_id'];
                }

                foreach ($subscription['actions'] ?? [] as $action) {
                    if (($action['key'] ?? null) === 'resubscribe' && !empty($subscription['plan_id'])) {
                        $resubscribePlanIds[] = (int) $subscription['plan_id'];
                    }
                }
            }
        }

        return $this->modalPlanRepository->findForAccountModal($siteIds, $resubscribePlanIds);
    }
}
