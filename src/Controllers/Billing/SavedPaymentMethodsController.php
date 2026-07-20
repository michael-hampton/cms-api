<?php

namespace App\Controllers\Billing;

use App\Controllers\Controller;
use App\DTO\Billing\PaymentMethodDto;
use App\Events\Billing\DefaultPaymentMethodChanged;
use App\Events\Billing\PaymentMethodAdded;
use App\Events\Billing\PaymentMethodRemoved;
use App\Events\Billing\SubscriptionPaymentMethodChanged;
use App\Framework\Events\EventDispatcher;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Auth\Contracts\AuthenticatedMemberResolverInterface;
use App\Services\Billing\Stripe\PaymentMethodSubscriptionUsageResolver;
use App\Services\Billing\Stripe\StripeCustomerPaymentMethodService;
use App\Services\Billing\Stripe\StripePaymentMethodWarningService;

/**
 * Single source of truth for a member's saved (Stripe) payment methods.
 *
 * Both the PressStack account area (/press-stack/account/payment-methods,
 * cross-site) and the site-scoped member area (/{site}/member/payment-methods)
 * are routed to this controller. Neither area has its own copy of this
 * logic any more - see routes/web.php and routes/api.php.
 *
 * The two areas differ only in:
 *   - how the member is scoped (single site vs cross-site), and
 *   - which layout/header wraps the shared payment-methods panel view.
 *
 * Business logic (fetching, adding, removing, defaulting) lives entirely
 * in StripeCustomerPaymentMethodService. Status derivation (active /
 * expiring / expired) lives in StripePaymentMethodWarningService.
 * Subscription <-> payment method usage (counts, reassignment) lives in
 * PaymentMethodSubscriptionUsageResolver. This controller only
 * orchestrates the request/response and emits analytics events - it does
 * not contain calculation logic itself.
 */
class SavedPaymentMethodsController extends Controller
{
    public function __construct(
        private readonly StripeCustomerPaymentMethodService     $paymentMethodService,
        private readonly StripePaymentMethodWarningService      $statusResolver,
        private readonly PaymentMethodSubscriptionUsageResolver $usageResolver,
        private readonly EventDispatcher                        $events,
        private readonly AuthenticatedMemberResolverInterface   $memberResolver,
        private readonly SubscriptionRepository                 $subscriptions,
    )
    {
        parent::__construct();
    }

    // ── Page renders ─────────────────────────────────────────────────────

    /**
     * GET /{site}/member/payment-methods
     */
    public function indexForMember(Request $request, string $site): Response
    {
        if (!$this->memberResolver->check()) {
            return $this->redirect("/{$site}/member/login");
        }

        return $this->renderIndex(
            view: 'member/subscriptions/payment-methods',
            extra: ['site' => SiteContext::get()],
        );
    }

    /**
     * Note: the PressStack "Payment Methods" page
     * (GET /press-stack/account/payment-methods) is still rendered by
     * ShopAccountController::paymentMethods() because that page also needs
     * addresses/countries context for its Addresses tab, and it renders the
     * same billing.php view. billing.php's payment-methods panel is now the
     * shared shared/billing/_payment_methods_panel.php partial, driven by
     * public/js/saved-payment-methods.js, which fetches everything client-side
     * from the JSON actions below - so no server-side payment method data
     * needs to be injected by that controller either.
     */
    private function renderIndex(string $view, array $extra = []): Response
    {
        $member = $this->memberResolver->resolve();
        $result = $this->paymentMethodService->getCustomerPaymentMethods($member);
        $warningsResult = $this->statusResolver->getPaymentMethodsWithWarnings($result);
        $usage = $member ? $this->usageResolver->usageByPaymentMethod($member) : [];

        return $this->view($view, [
            ...$extra,
            'member' => $member,
            'paymentMethods' => $result['payment_methods'] ?? [],
            'paymentMethodPayloads' => $this->payloads($result['payment_methods'] ?? [], $usage),
            'defaultPaymentMethodId' => $result['default_payment_method_id'] ?? null,
            'warnings' => $warningsResult['warnings'] ?? [],
            'hasWarnings' => $warningsResult['has_warnings'] ?? false,
        ]);
    }

    // ── JSON actions (shared by both areas) ─────────────────────────────

    /**
     * @param PaymentMethodDto[] $methods
     * @param array<string, array{count: int, subscriptions: array}> $usage
     */
    private function payloads(array $methods, array $usage = []): array
    {
        return array_map(
            function (PaymentMethodDto $method) use ($usage): array {
                $methodUsage = $usage[$method->id] ?? ['count' => 0, 'subscriptions' => []];

                return [
                    ...$method->toArray(),
                    'status' => $this->statusResolver->statusFor($method)->value,
                    'subscription_count' => $methodUsage['count'],
                    'in_use' => $methodUsage['count'] > 0,
                    'subscription_ids' => array_column($methodUsage['subscriptions'], 'id'),
                ];
            },
            $methods
        );
    }

    /**
     * GET /api/{site}/member/payment-methods
     * GET /press-stack/account/billing/payment-methods
     */
    public function list(Request $request): JsonResponse
    {
        $member = $this->authenticatedMember();

        if (!$member) {
            return $this->unauthorised();
        }

        $result = $this->paymentMethodService->getCustomerPaymentMethods($member);

        if (!($result['success'] ?? true)) {
            return JsonResponse::json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to fetch payment methods.',
            ], 502);
        }

        $usage = $this->usageResolver->usageByPaymentMethod($member);

        return JsonResponse::json([
            'success' => true,
            'payment_methods' => $this->payloads($result['payment_methods'] ?? [], $usage),
            'default_payment_method_id' => $result['default_payment_method_id'] ?? null,
        ]);
    }

    private function authenticatedMember(): ?Member
    {
        return $this->memberResolver->resolve();
    }

    private function unauthorised(): JsonResponse
    {
        return JsonResponse::json(['success' => false, 'message' => 'Unauthorised.'], 401);
    }

    /**
     * POST /{site}/member/payment-methods/setup-intent
     * POST /press-stack/account/billing/setup-intent
     *
     * Step 1 of adding a card: mints a Stripe SetupIntent so the card
     * number/CVC are confirmed directly with Stripe.js and never touch
     * our servers (AC: raw card details are not sent to the backend).
     */
    public function createSetupIntent(Request $request): JsonResponse
    {
        $member = $this->authenticatedMember();

        if (!$member) {
            return $this->unauthorised();
        }

        $result = $this->paymentMethodService->createSetupIntent($member);

        return JsonResponse::json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * POST /{site}/member/payment-methods
     * POST /press-stack/account/billing/payment-methods
     *
     * Step 2: finalises a confirmed SetupIntent into a saved payment method.
     * Expects `name_on_card` (required, AC: name on card is required) in
     * addition to `setup_intent_id` / `set_default`.
     */
    public function store(Request $request): JsonResponse
    {
        $member = $this->authenticatedMember();

        if (!$member) {
            return $this->unauthorised();
        }

        $setupIntentId = trim((string)$request->input('setup_intent_id', ''));
        $setDefault = (bool)$request->input('set_default', false);
        $nameOnCard = trim((string)$request->input('name_on_card', ''));

        $errors = $this->validateCardFormInput($setupIntentId, $nameOnCard);
        if ($errors !== []) {
            return JsonResponse::json(['success' => false, 'message' => $errors[0], 'errors' => $errors], 422);
        }

        $result = $this->paymentMethodService->finaliseSetupIntent($member, $setupIntentId, $setDefault);

        if (!($result['success'] ?? false)) {
            return JsonResponse::json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to add payment method.',
            ], 422);
        }

        $paymentMethod = $result['payment_method'] ?? null;
        $paymentMethodId = $paymentMethod instanceof PaymentMethodDto ? $paymentMethod->id : '';

        $this->events->dispatch(new PaymentMethodAdded(
            memberId: $member->id,
            paymentMethodId: $paymentMethodId,
            setAsDefault: $setDefault,
            source: $this->requestSource($request),
        ));

        return JsonResponse::json([
            'success' => true,
            'message' => 'Payment method added successfully.',
            'payment_method' => $paymentMethod instanceof PaymentMethodDto ? [
                ...$paymentMethod->toArray(),
                'status' => $this->statusResolver->statusFor($paymentMethod)->value,
            ] : null,
        ]);
    }

    /**
     * @return string[] validation error messages, empty when valid
     */
    private function validateCardFormInput(string $setupIntentId, string $nameOnCard): array
    {
        $errors = [];

        if ($setupIntentId === '') {
            $errors[] = 'SetupIntent ID is required.';
        }

        if ($nameOnCard === '') {
            $errors[] = 'Name on card is required.';
        }

        return $errors;
    }

    // ── Shared helpers ───────────────────────────────────────────────────

    private function requestSource(Request $request): string
    {
        $path = parse_url($request->getUri(), PHP_URL_PATH) ?: '';

        return str_starts_with($path, '/press-stack/') ? 'press-stack' : 'member';
    }

    /**
     * POST /{site}/member/payment-methods/{id}/set-default
     * POST /press-stack/account/billing/payment-methods/set-default
     */
    public function setDefault(Request $request, ?string $paymentMethodId = null): JsonResponse
    {
        $member = $this->authenticatedMember();

        if (!$member) {
            return $this->unauthorised();
        }

        $paymentMethodId ??= (string)$request->input('payment_method_id', '');

        if (!$member->stripe_customer_id) {
            return JsonResponse::json(['success' => false, 'message' => 'No billing customer found.'], 404);
        }

        if ($paymentMethodId === '' || $paymentMethodId === null) {
            return JsonResponse::json(['success' => false, 'message' => 'Payment method ID is required.'], 422);
        }

        $result = $this->paymentMethodService->setDefaultPaymentMethod((string)$member->stripe_customer_id, $paymentMethodId);

        if ($result['error_code'] === 'unauthorized') {
            return $this->jsonResponse(['Unauthrised'], 404);
        }

        if (!($result['success'] ?? false)) {
            return JsonResponse::json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to update default payment method.',
            ], ($result['error_code'] ?? null) === 'unauthorized' ? 403 : 422);
        }

        $this->events->dispatch(new DefaultPaymentMethodChanged(
            memberId: $member->id,
            paymentMethodId: $paymentMethodId,
            source: $this->requestSource($request),
        ));

        return JsonResponse::json([
            'success' => true,
            'message' => 'Default payment method updated. This applies to future renewals; subscriptions already assigned to a different card are not affected.',
        ]);
    }

    /**
     * DELETE /{site}/member/payment-methods/{id}
     * POST   /press-stack/account/billing/payment-methods/remove (payment_method_id in body)
     */
    public function destroy(Request $request, ?string $paymentMethodId = null): JsonResponse
    {
        $member = $this->authenticatedMember();

        if (!$member) {
            return $this->unauthorised();
        }

        $paymentMethodId ??= (string)$request->input('payment_method_id', '');

        if ($paymentMethodId === '' || $paymentMethodId === null) {
            return JsonResponse::json(['success' => false, 'message' => 'Payment method ID is required.'], 422);
        }

        $usage = $this->usageResolver->usageByPaymentMethod($member);
        $inUseCount = $usage[$paymentMethodId]['count'] ?? 0;

        if ($inUseCount > 0) {
            return JsonResponse::json([
                'success' => false,
                'error_code' => 'in_use',
                'message' => $inUseCount === 1
                    ? 'This card pays for 1 subscription. Move it to another payment method before removing this card.'
                    : "This card pays for {$inUseCount} subscriptions. Move them to another payment method before removing this card.",
                'subscriptions' => $usage[$paymentMethodId]['subscriptions'] ?? [],
            ], 422);
        }

        $result = $this->paymentMethodService->removePaymentMethod($member, $paymentMethodId);

        if ($result['error_code'] === 'unauthorized') {
            return $this->jsonResponse(['Unauthrised'], 404);
        }

        if (!($result['success'] ?? false)) {
            $statusCode = ($result['error_code'] ?? null) === 'unauthorized' ? 403 : 422;

            return JsonResponse::json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to remove payment method.',
            ], $statusCode);
        }

        $this->events->dispatch(new PaymentMethodRemoved(
            memberId: $member->id,
            paymentMethodId: $paymentMethodId,
            source: $this->requestSource($request),
        ));

        return JsonResponse::json(['success' => true, 'message' => 'Payment method removed successfully.']);
    }

    /**
     * Replace ("update") a payment method - per UX guidance this is never
     * an in-place edit. It attaches a new card, moves every subscription
     * that was paying with the old card onto the new one, then detaches
     * the old card once nothing depends on it any more.
     *
     * POST /{site}/member/payment-methods/{id}/update
     * POST /press-stack/account/billing/payment-methods/{id}/update
     */
    public function replace(Request $request, string $paymentMethodId): JsonResponse
    {
        $member = $this->authenticatedMember();

        if (!$member) {
            return $this->unauthorised();
        }

        $setupIntentId = trim((string)$request->input('setup_intent_id', ''));
        $setDefault = (bool)$request->input('set_default', false);
        $nameOnCard = trim((string)$request->input('name_on_card', ''));

        $errors = $this->validateCardFormInput($setupIntentId, $nameOnCard);
        if ($errors !== []) {
            return JsonResponse::json(['success' => false, 'message' => $errors[0], 'errors' => $errors], 422);
        }

        // Usage must be captured before the old card is touched.
        $usage = $this->usageResolver->usageByPaymentMethod($member);
        $affectedSubscriptions = $usage[$paymentMethodId]['subscriptions'] ?? [];

        $addResult = $this->paymentMethodService->finaliseSetupIntent($member, $setupIntentId, $setDefault);

        if (!($addResult['success'] ?? false)) {
            return JsonResponse::json([
                'success' => false,
                'message' => $addResult['message'] ?? 'Failed to add the replacement card.',
            ], 422);
        }

        $newPaymentMethod = $addResult['payment_method'] ?? null;
        $newPaymentMethodId = $newPaymentMethod instanceof PaymentMethodDto ? $newPaymentMethod->id : '';

        $this->events->dispatch(new PaymentMethodAdded(
            memberId: $member->id,
            paymentMethodId: $newPaymentMethodId,
            setAsDefault: $setDefault,
            source: $this->requestSource($request),
        ));

        if ($affectedSubscriptions !== [] && $newPaymentMethodId !== '') {
            $this->usageResolver->reassignSubscriptions(
                array_column($affectedSubscriptions, 'stripe_subscription_id'),
                $newPaymentMethodId,
            );

            foreach ($affectedSubscriptions as $subscription) {
                $this->events->dispatch(new SubscriptionPaymentMethodChanged(
                    memberId: $member->id,
                    subscriptionId: $subscription['id'],
                    paymentMethodId: $newPaymentMethodId,
                    source: $this->requestSource($request),
                ));
            }
        }

        // The old card is only detached once every subscription that
        // depended on it has been moved onto the new one, so this should
        // now succeed - removePaymentMethod is still the final authority
        // (it also guards against removing the customer's only card).
        $removeResult = $this->paymentMethodService->removePaymentMethod($member, $paymentMethodId);

        if ($removeResult['success'] ?? false) {
            $this->events->dispatch(new PaymentMethodRemoved(
                memberId: $member->id,
                paymentMethodId: $paymentMethodId,
                source: $this->requestSource($request),
            ));
        }

        $movedCount = count($affectedSubscriptions);
        $message = match (true) {
                $removeResult['success'] ?? false && $movedCount > 0 => "Payment method updated. {$movedCount} subscription" . ($movedCount === 1 ? '' : 's') . ' moved to the new card and the old card was removed.',
                $removeResult['success'] ?? false => 'Payment method updated successfully.',
            default => 'New card added. The old card is still in use elsewhere so it has not been removed.',
        };

        return JsonResponse::json(['success' => true, 'message' => $message]);
    }

    /**
     * Change which saved payment method pays for a single subscription,
     * from the subscription's own "manage subscription" screen.
     *
     * POST /press-stack/account/subscriptions/{subscriptionId}/payment-method
     * POST /{site}/member/subscriptions/{subscriptionId}/payment-method
     */
    public function changeSubscriptionPaymentMethod(Request $request, string $id): JsonResponse
    {
        $member = $this->authenticatedMember();

        if (!$member) {
            return $this->unauthorised();
        }

        $paymentMethodId = trim((string)$request->input('payment_method_id', ''));

        if ($paymentMethodId === '') {
            return JsonResponse::json(['success' => false, 'message' => 'Payment method ID is required.'], 422);
        }

        $subscription = $this->subscriptions->find((int)$id);

        if (!$subscription || (int)$subscription->member_id !== (int)$member->id || !$subscription->stripe_subscription_id) {
            return JsonResponse::json(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        if (!$this->paymentMethodService->verifyPaymentMethodOwnership($member, $paymentMethodId)) {
            return JsonResponse::json(['success' => false, 'message' => 'That payment method is not available on your account.'], 403);
        }

        try {
            $this->usageResolver->reassignSubscriptions([$subscription->stripe_subscription_id], $paymentMethodId);
        } catch (\Throwable) {
            return JsonResponse::json(['success' => false, 'message' => 'Failed to update the payment method for this subscription.'], 502);
        }

        $this->events->dispatch(new SubscriptionPaymentMethodChanged(
            memberId: $member->id,
            subscriptionId: (int)$subscription->id,
            paymentMethodId: $paymentMethodId,
            source: $this->requestSource($request),
        ));

        return JsonResponse::json(['success' => true, 'message' => 'Payment method updated for this subscription.']);
    }
}
