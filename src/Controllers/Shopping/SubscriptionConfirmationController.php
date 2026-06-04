<?php

namespace App\Controllers\Shopping;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Order;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * Renders the post-checkout subscription confirmation page.
 *
 * Handles both route shapes the JS checkout produces:
 *
 *   Single:   GET /{site}/press-stack/{id}
 *   Multiple: GET /{site}/press-stack/multiple?ids=1,2,3
 *
 * Both resolve to the same view. The view always receives a $subscriptions
 * array (even for one item) plus a $subscription convenience alias for the
 * primary/first entry, so single-subscription templates need no changes.
 *
 * Route registration (order matters — literal segment before wildcard):
 *   $router->get('/{site}/press-stack/multiple', [SubscriptionConfirmationController::class, 'show']);
 *   $router->get('/{site}/press-stack/{id}',     [SubscriptionConfirmationController::class, 'show']);
 */
class SubscriptionConfirmationController extends Controller
{
    public function __construct(
        private readonly SubscriptionRepository     $subscriptionRepository,
        private readonly SubscriptionPlanRepository $subscriptionPlanRepository,
        private readonly OrderRepository            $orderRepository,
    )
    {
        parent::__construct();
    }

    /**
     * Single entry point for both route shapes.
     *
     * @param Request $request
     * @param int|null $id Populated by the router for /{site}/press-stack/{id}.
     *                      Null when the router matched the literal "multiple" segment.
     */
    public function show(Request $request, ?int $id = null): mixed
    {
        /* ── 1. Collect all requested IDs ────────────────────── */
        $ids = $this->resolveIds($request, $id);

        if (empty($ids)) {
            return $this->redirect('/');
        }

        /* ── 2. Load subscriptions — single query ───────────── */
        $subscriptions = array_values(
            $this->subscriptionRepository->findMany($ids)->toArray()
        );

        if (empty($subscriptions)) {
            return $this->redirect('/');
        }

        /* ── 3. Authorisation ────────────────────────────────── */
        if (MemberAuth::check()) {
            $memberId = (int)MemberAuth::getMember()->id;
            foreach ($subscriptions as $subscription) {
                if ((int)$subscription['member_id'] !== $memberId && (int)($subscription['gifted_by_member_id'] ?? 0) !== $memberId) {
                    return $this->redirect('/');
                }
            }
        }
        // Anonymous / OTP checkout: no session member — allow through.
        // The page reveals only what was just purchased and the URL is unguessable.

        /* ── 4. Attach resolved plan to each subscription — single query ── */
        $planIds = array_values(array_unique(array_filter(array_map(
            fn(array $subscription) => (int)($subscription['plan_id'] ?? $subscription['subscription_plan_id'] ?? 0),
            $subscriptions
        ))));

        $plansById = [];
        foreach ($this->subscriptionPlanRepository->findMany($planIds)->all() as $plan) {
            $plansById[(int)$plan->id] = $plan;
        }

        $subscriptions = array_map(function ($subscription) use ($plansById) {
            $planId = (int)($subscription['plan_id'] ?? $subscription['subscription_plan_id'] ?? 0);
            $subscription['resolvedPlan'] = $plansById[$planId]
                ?? null;
            return $subscription;
        }, $subscriptions);

        /* ── 5. Resolve the shared order ─────────────────────── */
        // Multi-subscription checkouts share a single order. Use the first
        // subscription's order_id as the anchor; fall back to the query string.
        $order = $this->resolveOrder($subscriptions[0], $request->query('order_id'));

        /* ── 6. Build view payload ───────────────────────────── */
        $primary = $subscriptions[0];

        $plan = $primary['resolvedPlan'];

        if (!$plan) {
            return $this->redirect('/');
        }

        return $this->view('checkout/subscription-confirmation', [
            // Always an array — the view iterates for multi, uses [0] for single
            'subscriptions' => $subscriptions,
            'isMultiple' => count($subscriptions) > 1,

            // Single / primary convenience aliases (view backwards-compatible)
            'subscription' => $primary,
            'plan' => $plan,
            'order' => $order,
            'subscriptionId' => $primary['id'],

            // Contact & delivery
            'customerEmail' => $this->resolveEmail($primary, $order),
            'shippingAddress' => $this->resolveShippingAddress($primary, $order),
            'paymentMethod' => $order?->payment_method ?? $primary['payment_method'] ?? null,
            'isDigital' => $this->isDigitalDelivery($primary, $plan),

            // Financials — order totals preferred; fall back to plan price
            'subtotal' => $order?->subtotal ?? $plan->price ?? null,
            'tax' => $order?->tax ?? null,
            'shipping' => $order?->shipping ?? 0,
            'total' => $order?->total ?? $plan->price ?? null,

            // Dates
            'startDate' => $primary['starts_at'] ?? $primary['start_date'] ?? now(),
            'nextBillingDate' => $primary['next_billing_date'] ?? $primary['renews_at'] ?? null,

            // Site
            'siteSlug' => SiteContext::slug(),
        ]);
    }

    /* ── Private helpers ──────────────────────────────────────── */

    /**
     * Normalise the two possible ID sources into a clean int[].
     *
     * Route A — single   /{site}/press-stack/123
     *   Router populates $id; ?ids is absent.
     *
     * Route B — multiple /{site}/press-stack/multiple?ids=1,2,3
     *   Router matched literal "multiple" so $id is null; parse ?ids.
     */
    private function resolveIds(Request $request, ?int $id): array
    {
        if ($id !== null) {
            return [$id];
        }

        $raw = $request->query('ids', '');

        if (empty($raw)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                fn(string $s) => (int)trim($s),
                explode(',', $raw)
            ),
            fn(int $i) => $i > 0
        ));
    }

    /**
     * Resolve the associated order.
     * Prefers the subscription's own order_id; falls back to a query-string
     * order number for cases where the relationship isn't yet persisted.
     */
    private function resolveOrder(mixed $subscription, ?string $orderNumber): mixed
    {
        $order = Order::where('one_time_subscription_id', $subscription['id'])->first();

        if ($order) {
            return $order;
        }

        if ($orderNumber) {
            return $this->orderRepository->findByOrderNumber($orderNumber);
        }

        return null;
    }

    private function resolveShippingAddress(mixed $subscription, mixed $order): array|object|null
    {
        return $subscription['shipping_address'] ?? null
            ?? $order?->shipping_address
            ?? null;
    }

    private function resolveEmail(mixed $subscription, mixed $order): string
    {
        if (MemberAuth::check()) {
            return MemberAuth::getMember()->email ?? '';
        }

        $member = $subscription['member'] ?? null;
        $memberEmail = is_array($member)
            ? ($member['email'] ?? null)
            : ($member?->email ?? null);

        return $order?->user?->email
            ?? $memberEmail
            ?? $subscription['email'] ?? null
            ?? '';
    }

    private function isDigitalDelivery(mixed $subscription, mixed $plan): bool
    {
        $deliveryType = $subscription['delivery_type']
            ?? $subscription['options']['delivery_type']
            ?? null;

        if ($deliveryType !== null) {
            return in_array(strtolower($deliveryType), ['digital', 'print_and_digital'], true);
        }

        return $plan?->hasDigitalOption() ?? false;
    }
}
