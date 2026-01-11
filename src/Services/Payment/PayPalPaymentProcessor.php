<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Members\PaymentRepository;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;

class PayPalPaymentProcessor
{
    private PayPalHttpClient $client;
    private string $restBase; // Base URL for REST API (api-m.paypal.com)

    public function __construct(
        private readonly PaymentRepository $paymentRepository
    )
    {
//        $clientId = $_ENV['PAYPAL_CLIENT_ID'] ?? config('payment.paypal.client_id');
//        $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET'] ?? config('payment.paypal.client_secret');
//        $mode = $_ENV['PAYPAL_MODE'] ?? config('payment.paypal.mode', 'sandbox');
//
//        $environment = $mode === 'live'
//            ? new ProductionEnvironment($clientId, $clientSecret)
//            : new SandboxEnvironment($clientId, $clientSecret);
//
//        $this->client = new PayPalHttpClient($environment);
//
//        $this->restBase = $mode === 'live'
//            ? 'https://api-m.paypal.com'
//            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * ---------------------------------------------------------
     *  SUBSCRIPTION PAYMENT
     * ---------------------------------------------------------
     */
    public function processSubscriptionPayment(
        Subscription     $subscription,
        SubscriptionPlan $plan,
        array            $data
    ): array
    {
        try {
            // Create PayPal Plan or reuse existing
            $planId = $this->getOrCreatePayPalPlan($plan);

            // Create subscription
            $res = $this->paypalRequest(
                'POST',
                '/v1/billing/subscriptions',
                [
                    'plan_id' => $planId,
                    'custom_id' => (string)$subscription->id,
                    'subscriber' => [
                        'name' => [
                            'given_name' => $subscription->member->first_name ?? 'Customer',
                            'surname' => $subscription->member->last_name ?? '',
                        ],
                        'email_address' => $subscription->member->email,
                    ],
                    'application_context' => [
                        'brand_name' => config('app.name'),
                        'user_action' => 'SUBSCRIBE_NOW',
                        'return_url' => $this->getReturnUrl($subscription),
                        'cancel_url' => $this->getCancelUrl($subscription),
                    ],
                ]
            );

            $paypalSubscriptionId = $res->result->id;

            // Save payment record
            $payment = Payment::create([
                'subscription_id' => $subscription->id,
                'site_id' => $subscription->site_id,
                'payment_method' => 'paypal',
                'payment_provider' => 'paypal',
                'transaction_id' => $paypalSubscriptionId,
                'status' => 'pending',
                'amount' => $plan->price,
                'currency' => strtoupper($plan->currency),
                'metadata' => [
                    'paypal_subscription_id' => $paypalSubscriptionId,
                    'paypal_plan_id' => $planId,
                ]
            ]);

            // Find approval URL
            $approvalUrl = null;
            foreach ($res->result->links as $link) {
                if ($link->rel === 'approve') {
                    $approvalUrl = $link->href;
                }
            }

            return [
                'success' => true,
                'redirect_url' => $approvalUrl,
                'requires_redirect' => true,
                'payment_id' => $payment->id,
                'paypal_subscription_id' => $paypalSubscriptionId
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'PayPal subscription failed.'];
        }
    }

    /**
     * ---------------------------------------------------------
     *  CREATE PAYPAL PRODUCT + PLAN
     * ---------------------------------------------------------
     */
    private function getOrCreatePayPalPlan(SubscriptionPlan $plan): string
    {
        if ($plan->paypal_plan_id) {
            return $plan->paypal_plan_id;
        }

        // 1) Create Product
        $productRes = $this->paypalRequest(
            'POST',
            '/v1/catalogs/products',
            [
                'name' => $plan->name,
                'description' => $plan->description,
                'type' => 'SERVICE',
                'category' => 'SOFTWARE'
            ]
        );

        $productId = $productRes->result->id;

        // 2) Create Plan
        $planRes = $this->paypalRequest(
            'POST',
            '/v1/billing/plans',
            [
                'product_id' => $productId,
                'name' => $plan->name,
                'status' => 'ACTIVE',
                'billing_cycles' => [
                    [
                        'frequency' => [
                            'interval_unit' => $this->mapBillingPeriodToInterval($plan->billing_period),
                            'interval_count' => $this->getIntervalCount($plan->billing_period),
                        ],
                        'tenure_type' => 'REGULAR',
                        'sequence' => 1,
                        'total_cycles' => 0,
                        'pricing_scheme' => [
                            'fixed_price' => [
                                'value' => (string)$plan->price,
                                'currency_code' => strtoupper($plan->currency),
                            ]
                        ]
                    ]
                ],
                'payment_preferences' => [
                    'auto_bill_outstanding' => true,
                    'payment_failure_threshold' => 3
                ]
            ]
        );

        $paypalPlanId = $planRes->result->id;

        $plan->update(['paypal_plan_id' => $paypalPlanId]);

        return $paypalPlanId;
    }

    /**
     * ---------------------------------------------------------
     *  INTERNAL PAYPAL CALLER (used for Subscriptions API)
     * ---------------------------------------------------------
     */
    private function paypalRequest(string $method, string $endpoint, array $body = [])
    {
        $req = new \PayPalHttp\HttpRequest($endpoint, $method);
        $req->headers['Content-Type'] = 'application/json';

        if ($body !== null) {
            $req->body = json_encode($body);
        }

        return $this->client->execute($req);
    }

    /**
     * ---------------------------------------------------------
     *  UTILITIES
     * ---------------------------------------------------------
     */
    private function mapBillingPeriodToInterval(string $period): string
    {
        return match ($period) {
            'monthly' => 'MONTH',
            'quarterly' => 'MONTH',
            'yearly' => 'YEAR',
            default => 'MONTH'
        };
    }

    private function getIntervalCount(string $period): int
    {
        return $period === 'quarterly' ? 3 : 1;
    }

    private function getReturnUrl(Subscription $subscription): string
    {
        return config('app.url') . '/checkout/paypal/success?subscription_id=' . $subscription->id;
    }

    private function getCancelUrl(Subscription $subscription): string
    {
        return config('app.url') . '/checkout/paypal/cancel?subscription_id=' . $subscription->id;
    }

    /**
     * ---------------------------------------------------------
     *  ONE-TIME PAYMENT
     * ---------------------------------------------------------
     */
    public function processOneTimePayment(array $orderData, array $paymentData): array
    {
        try {
            $req = new OrdersCreateRequest();
            $req->prefer('return=representation');
            $req->body = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => strtoupper($orderData['currency'] ?? 'USD'),
                            'value' => (string)$orderData['amount']
                        ]
                    ]
                ],
                'application_context' => [
                    'return_url' => $orderData['return_url'],
                    'cancel_url' => $orderData['cancel_url']
                ]
            ];

            $res = $this->client->execute($req);

            foreach ($res->result->links as $link) {
                if ($link->rel === 'approve') {
                    return [
                        'success' => true,
                        'requires_redirect' => true,
                        'approval_url' => $link->href,
                        'order_id' => $res->result->id
                    ];
                }
            }

            return ['success' => false, 'message' => 'PayPal approval link missing.'];

        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'PayPal order creation failed.'];
        }
    }

    public function captureOrder(string $orderId): array
    {
        try {
            $res = $this->client->execute(new OrdersCaptureRequest($orderId));

            return [
                'success' => true,
                'transaction_id' => $res->result->id,
                'status' => $res->result->status
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Failed to capture order.'];
        }
    }

    /**
     * ---------------------------------------------------------
     *  SUBSCRIPTION VERIFY + CANCEL
     * ---------------------------------------------------------
     */
    public function verifySubscription(string $subscriptionId): array
    {
        try {
            $res = $this->paypalRequest('GET', "/v1/billing/subscriptions/{$subscriptionId}");

            return ['success' => true, 'status' => $res->result->status];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Unable to verify subscription.'];
        }
    }

    public function cancelSubscription(string $subscriptionId, string $reason = ''): array
    {
        try {
            $this->paypalRequest(
                'POST',
                "/v1/billing/subscriptions/{$subscriptionId}/cancel",
                ['reason' => $reason ?: 'Customer requested cancellation']
            );

            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Failed to cancel subscription.'];
        }
    }
}
