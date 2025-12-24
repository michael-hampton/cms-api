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
        $orderId = $request->input('order_id');
        $siteId = SiteContext::getId();

        // Handle both single and multiple subscriptions
        $subscriptionIds = $request->input('subscription_ids');
        $subscriptionId = $request->input('subscription_id');

        if (!$paymentIntentId || !$orderId) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Missing required parameters'
            ], 400);
        }

        if (empty($subscriptionIds) && empty($subscriptionId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Missing subscription information'
            ], 400);
        }

        // Convert to array for uniform handling
        if (!empty($subscriptionId)) {
            $subscriptionIds = [$subscriptionId];
        }

        $result = $this->stripeProcessor->handleOneTimeSubscriptionPayment(
            $paymentIntentId,
            $orderId,
            $siteId,
            $subscriptionIds
        );

        if ($result['success']) {
            // Activate all subscriptions
            foreach ($subscriptionIds as $subId) {
                $this->subscriptionService->activateSubscription($subId, $orderId);
            }

            // Update order status
            \App\Models\Order::where('id', $orderId)->update([
                'status' => 'completed',
                'payment_status' => 'paid'
            ]);
        }

        return $this->jsonResponse($result, $result['success'] ? 200 : 400);
    }

    public function showMultiple(Request $request)
    {
        $subscriptionIds = $request->input('ids');

        if (empty($subscriptionIds)) {
            return $this->redirect('/');
        }

        $subscriptions = [];
        foreach ($subscriptionIds as $id) {
            $details = $this->subscriptionService->getSubscriptionWithDetails($id);
            if ($details) {
                $subscriptions[] = $details;
            }
        }

        if (empty($subscriptions)) {
            return $this->redirect('/');
        }

        return $this->view('subscriptions/onetime/multiple-details', [
            'subscriptions' => $subscriptions
        ]);
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