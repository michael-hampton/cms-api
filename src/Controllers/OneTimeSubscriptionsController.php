<?php

namespace App\Controllers;

use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Services\OneTimeSubscriptionCheckoutService;
use App\Services\OneTimeSubscriptionService;
use App\Services\Payment\StripePaymentProcessor;

class OneTimeSubscriptionsController extends Controller
{
    public function __construct(
        private readonly OneTimeSubscriptionService         $subscriptionService,
        private readonly OneTimeSubscriptionCheckoutService $checkoutService,
        private readonly StripePaymentProcessor             $stripeProcessor
    )
    {
        parent::__construct();
    }

    public function index()
    {
        $siteId = SiteContext::getId();
        $plans = $this->subscriptionService->getOneTimePlans($siteId);

        return $this->view('subscriptions/onetime/index', [
            'plans' => $plans,
            'stripe_key' => $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? config('payment.stripe.publishable_key')
        ]);
    }

    public function checkout(Request $request)
    {
        $data = $request->all();
        $siteId = SiteContext::getId();

        $result = $this->checkoutService->processCheckout($data, $siteId);

        return $this->jsonResponse($result, $result['success'] ? 200 : 400);
    }

    public function confirmPayment(Request $request)
    {
        $paymentIntentId = $request->input('payment_intent_id');
        $subscriptionId = $request->input('subscription_id');
        $orderId = $request->input('order_id');
        $siteId = SiteContext::getId();

        if (!$paymentIntentId || !$subscriptionId || !$orderId) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Missing required parameters'
            ], 400);
        }

        $result = $this->stripeProcessor->handleOneTimeSubscriptionPayment(
            $paymentIntentId,
            $subscriptionId,
            $orderId,
            $siteId
        );

        if ($result['success']) {
            // Activate subscription
            $this->subscriptionService->activateSubscription($subscriptionId, $orderId);

            // Update order status
            \App\Models\Order::where('id', $orderId)->update([
                'status' => 'completed',
                'payment_status' => 'paid'
            ]);
        }

        return $this->jsonResponse($result, $result['success'] ? 200 : 400);
    }

    public function show(int $id)
    {
        $details = $this->subscriptionService->getSubscriptionWithDetails($id);

        if (!$details) {
            return $this->redirect('/');
        }

        return $this->view('subscriptions/onetime/details', $details);
    }
}