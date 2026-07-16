<?php

namespace App\Controllers\Billing;

use App\Controllers\Controller;
use App\DTO\Billing\PaymentMethodDto;
use App\Events\Billing\DefaultPaymentMethodChanged;
use App\Events\Billing\PaymentMethodAdded;
use App\Events\Billing\PaymentMethodRemoved;
use App\Framework\Events\EventDispatcher;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Services\Auth\Contracts\AuthenticatedMemberResolverInterface;
use App\Services\Billing\Stripe\StripeCustomerPaymentMethodService;
use App\Services\Billing\Stripe\StripePaymentMethodWarningService;

/**
 * Single source of truth for a member's saved (Stripe) payment methods.
 *
 * Both the PressStack account area (/press-stack/account/payment-methods,
 * cross-site) and the site-scoped member area (/{site}/member/payment-methods)
 * are routed to this controller. Neither area has its own copy of this
 * logic any more - see routes/web.php and routes/subscription-account.php.
 *
 * The two areas differ only in:
 *   - how the member is scoped (single site vs cross-site), and
 *   - which layout/header wraps the shared payment-methods panel view.
 *
 * Business logic (fetching, adding, removing, defaulting) lives entirely
 * in StripeCustomerPaymentMethodService. Status derivation (active /
 * expiring / expired) lives entirely in StripePaymentMethodWarningService.
 * This controller only orchestrates the request/response and emits
 * analytics events - it does not contain calculation logic itself.
 */
class SavedPaymentMethodsController extends Controller
{
    public function __construct(
        private readonly StripeCustomerPaymentMethodService $paymentMethodService,
        private readonly StripePaymentMethodWarningService $statusResolver,
        private readonly EventDispatcher $events,
        private readonly AuthenticatedMemberResolverInterface $memberResolver,
    ) {
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

        return $this->view($view, [
            ...$extra,
            'member' => $member,
            'paymentMethods' => $result['payment_methods'] ?? [],
            'paymentMethodPayloads' => $this->payloads($result['payment_methods'] ?? []),
            'defaultPaymentMethodId' => $result['default_payment_method_id'] ?? null,
            'warnings' => $warningsResult['warnings'] ?? [],
            'hasWarnings' => $warningsResult['has_warnings'] ?? false,
        ]);
    }

    // ── JSON actions (shared by both areas) ─────────────────────────────

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

        return JsonResponse::json([
            'success' => true,
            'payment_methods' => $this->payloads($result['payment_methods'] ?? []),
            'default_payment_method_id' => $result['default_payment_method_id'] ?? null,
        ]);
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
     */
    public function store(Request $request): JsonResponse
    {
        $member = $this->authenticatedMember();

        if (!$member) {
            return $this->unauthorised();
        }

        $setupIntentId = trim((string) $request->input('setup_intent_id', ''));
        $setDefault = (bool) $request->input('set_default', false);

        if ($setupIntentId === '') {
            return JsonResponse::json([
                'success' => false,
                'message' => 'SetupIntent ID is required.',
            ], 422);
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
     * POST /{site}/member/payment-methods/{id}/set-default
     * POST /press-stack/account/billing/payment-methods/set-default
     */
    public function setDefault(Request $request, ?string $paymentMethodId = null): JsonResponse
    {
        $member = $this->authenticatedMember();

        if (!$member) {
            return $this->unauthorised();
        }

        $paymentMethodId ??= (string) $request->input('payment_method_id', '');

        if (!$member->stripe_customer_id) {
            return JsonResponse::json(['success' => false, 'message' => 'No billing customer found.'], 404);
        }

        if ($paymentMethodId === '' || $paymentMethodId === null) {
            return JsonResponse::json(['success' => false, 'message' => 'Payment method ID is required.'], 422);
        }

        $result = $this->paymentMethodService->setDefaultPaymentMethod((string) $member->stripe_customer_id, $paymentMethodId);

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

        return JsonResponse::json(['success' => true, 'message' => 'Default payment method updated.']);
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

        $paymentMethodId ??= (string) $request->input('payment_method_id', '');

        if ($paymentMethodId === '' || $paymentMethodId === null) {
            return JsonResponse::json(['success' => false, 'message' => 'Payment method ID is required.'], 422);
        }

        $result = $this->paymentMethodService->removePaymentMethod($member, $paymentMethodId);

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
     * an in-place edit. It attaches a new card and detaches the old one.
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

        $setupIntentId = trim((string) $request->input('setup_intent_id', ''));
        $setDefault = (bool) $request->input('set_default', false);

        if ($setupIntentId === '') {
            return JsonResponse::json(['success' => false, 'message' => 'SetupIntent ID is required.'], 422);
        }

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

        // The old card is only detached once the replacement is confirmed
        // attached, and only if it isn't the last method backing active
        // recurring billing (StripeCustomerPaymentMethodService enforces
        // this) - if it's still in use elsewhere the member keeps both
        // cards and can remove the old one once nothing depends on it.
        $removeResult = $this->paymentMethodService->removePaymentMethod($member, $paymentMethodId);

        if ($removeResult['success'] ?? false) {
            $this->events->dispatch(new PaymentMethodRemoved(
                memberId: $member->id,
                paymentMethodId: $paymentMethodId,
                source: $this->requestSource($request),
            ));
        }

        return JsonResponse::json([
            'success' => true,
            'message' => ($removeResult['success'] ?? false)
                ? 'Payment method updated successfully.'
                : 'New card added. The old card is still in use elsewhere so it has not been removed.',
        ]);
    }

    // ── Shared helpers ───────────────────────────────────────────────────

    private function authenticatedMember(): ?Member
    {
        return $this->memberResolver->resolve();
    }

    private function unauthorised(): JsonResponse
    {
        return JsonResponse::json(['success' => false, 'message' => 'Unauthorised.'], 401);
    }

    /**
     * @param PaymentMethodDto[] $methods
     */
    private function payloads(array $methods): array
    {
        return array_map(
            fn (PaymentMethodDto $method): array => [
                ...$method->toArray(),
                'status' => $this->statusResolver->statusFor($method)->value,
            ],
            $methods
        );
    }

    private function requestSource(Request $request): string
    {
        $path = parse_url($request->getUri(), PHP_URL_PATH) ?: '';

        return str_starts_with($path, '/press-stack/') ? 'press-stack' : 'member';
    }
}
