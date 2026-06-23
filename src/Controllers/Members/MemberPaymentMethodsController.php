<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\DTO\Billing\PaymentMethodDto;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Services\Billing\Stripe\StripeCustomerPaymentMethodService;
use App\Services\Billing\Stripe\StripePaymentMethodWarningService;

class MemberPaymentMethodsController extends Controller
{
    public function __construct(
        private readonly StripeCustomerPaymentMethodService $paymentMethodService,
        private readonly StripePaymentMethodWarningService $warningService,
    )
    {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $result = $this->paymentMethodService->getCustomerPaymentMethods($member);
        $warningsResult = $this->warningService->getPaymentMethodsWithWarnings($result);

        return $this->view('member/subscriptions/payment-methods', [
            'member' => $member,
            'site' => SiteContext::get(),
            'paymentMethods' => $result['payment_methods'] ?? [],
            'defaultPaymentMethodId' => $result['default_payment_method_id'] ?? null,
            'warnings' => $warningsResult['warnings'] ?? [],
            'hasWarnings' => $warningsResult['has_warnings'] ?? false
        ]);
    }

    public function getPaymentMethodsForMember()
    {
        $result = $this->paymentMethodService->getCustomerPaymentMethods(MemberAuth::getMember());

        return $this->jsonResponse([
            'payment_methods' => array_map(
                static fn (PaymentMethodDto $method) => $method->toArray(),
                $result['payment_methods'] ?? []
            ),
        ]);
    }

    public function store(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::getMember();
        $paymentMethodId = $request->input('payment_method_id');
        $setDefault = $request->input('set_default', false);

        $result = $this->paymentMethodService->addPaymentMethod($member, $paymentMethodId, $setDefault);

        if ($result['success']) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Payment method added successfully'
            ]);
        }

        return $this->jsonResponse([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to add payment method'
        ], 500);
    }

    public function setDefault(Request $request, string $paymentMethodId)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::getMember();

        if (!$member->stripe_customer_id) {
            return $this->jsonResponse(['success' => false, 'message' => 'No customer found'], 404);
        }

        $result = $this->paymentMethodService->setDefaultPaymentMethod($member->stripe_customer_id, $paymentMethodId);

        if ($result['success']) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Default payment method updated'
            ]);
        }

        return $this->jsonResponse([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to update default payment method'
        ], 500);
    }

    public function destroy(string $paymentMethodId)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::getMember();

        $result = $this->paymentMethodService->removePaymentMethod($member, $paymentMethodId);

        if ($result['success']) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Payment method removed successfully'
            ]);
        }

        $statusCode = $result['error_code'] === 'unauthorized' ? 403 : 500;

        return $this->jsonResponse([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to remove payment method'
        ], $statusCode);
    }

    public function update(Request $request, string $paymentMethodId)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::getMember();
        $newPaymentMethodId = $request->input('new_payment_method_id');
        $setDefault = $request->input('set_default', false);

        if (!$newPaymentMethodId) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'New payment method ID required'
            ], 400);
        }

        $removeResult = $this->paymentMethodService->removePaymentMethod($member, $paymentMethodId);

        if (!$removeResult['success']) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to remove old payment method'
            ], 500);
        }

        $addResult = $this->paymentMethodService->addPaymentMethod($member, $newPaymentMethodId, $setDefault);

        if ($addResult['success']) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Payment method updated successfully'
            ]);
        }

        return $this->jsonResponse([
            'success' => false,
            'message' => $addResult['message'] ?? 'Failed to add new payment method'
        ], 500);
    }
}
