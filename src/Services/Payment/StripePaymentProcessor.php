<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\PaymentRepository;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripePaymentProcessor
{
    private StripeClient $stripe;

    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        ?StripeClient                      $stripeClient = null
    )
    {
        if ($stripeClient) {
            $this->stripe = $stripeClient;
        } else {
            $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? config('payment.stripe.secret_key');
            $this->stripe = new StripeClient($secretKey);
        }
    }

    public function processSubscriptionPayment(
        Subscription     $subscription,
        SubscriptionPlan $plan,
        array            $data
    ): array
    {
        try {

            // Create or retrieve customer
            $customerId = $this->getOrCreateCustomer($subscription->member, $data);

            // Attach payment method to customer (from Stripe.js)
            if (!empty($data['payment_method_id'])) {
                $paymentMethodId = $data['payment_method_id'];

                // Attach payment method to customer
                $this->stripe->paymentMethods->attach($paymentMethodId, [
                    'customer' => $customerId
                ]);

                // Set as default payment method
                $this->stripe->customers->update($customerId, [
                    'invoice_settings' => [
                        'default_payment_method' => $paymentMethodId
                    ]
                ]);
            }

            // Create Stripe subscription
            $stripeSubscription = $this->createStripeSubscription(
                $customerId,
                $plan,
                $subscription
            );

            // Get the latest invoice and payment intent
            $latestInvoice = $stripeSubscription->latest_invoice;
            $paymentIntentId = null;
            $requiresAction = false;
            $clientSecret = null;

            if (is_string($latestInvoice)) {
                $invoice = $this->stripe->invoices->retrieve($latestInvoice, [
                    'expand' => ['payment_intent']
                ]);

                if ($invoice->payment_intent) {
                    $paymentIntent = $invoice->payment_intent;
                    $paymentIntentId = is_string($paymentIntent) ? $paymentIntent : $paymentIntent->id;

                    if (!is_string($paymentIntent)) {
                        $requiresAction = $paymentIntent->status === 'requires_action';
                        $clientSecret = $paymentIntent->client_secret;
                    }
                }
            } elseif (is_object($latestInvoice) && isset($latestInvoice->payment_intent)) {
                $paymentIntent = $latestInvoice->payment_intent;
                $paymentIntentId = is_string($paymentIntent) ? $paymentIntent : $paymentIntent->id;

                if (!is_string($paymentIntent)) {
                    $requiresAction = $paymentIntent->status === 'requires_action';
                    $clientSecret = $paymentIntent->client_secret;
                }
            }

            // Create payment record
            $payment = $this->paymentRepository->create(
                [
                    'subscription_id' => $subscription->id,
                    'site_id' => $subscription->site_id,
                    'payment_method' => 'stripe',
                    'payment_provider' => 'stripe',
                    'transaction_id' => is_string($latestInvoice) ? $latestInvoice : $latestInvoice->id,
                    'payment_intent_id' => $paymentIntentId,
                    'status' => $this->mapStripeStatus($stripeSubscription->status),
                    'amount' => $plan->price,
                    'currency' => strtoupper($plan->currency),
                    'metadata' => [
                        'subscription_id' => $subscription->id,
                        'plan_id' => $plan->id,
                        'billing_period' => $plan->billing_period,
                        'stripe_subscription_id' => $stripeSubscription->id,
                        'stripe_customer_id' => $customerId
                    ]
                ]
            );

            // Update payment status based on subscription status
            if ($stripeSubscription->status === 'active' && !$requiresAction) {
                $this->paymentRepository->update($payment->id, [
                    'status' => 'completed',
                    'paid_at' => date('Y-m-d H:i:s')
                ]);

                // Update subscription status
                $subscription->update(['status' => 'active']);
            }

            return [
                'success' => true,
                'payment_intent_id' => $paymentIntentId,
                'subscription_id' => $stripeSubscription->id,
                'status' => $stripeSubscription->status,
                'customer_id' => $customerId,
                'requires_action' => $requiresAction,
                'payment_intent_client_secret' => $clientSecret
            ];

        } catch (ApiErrorException $e) {
            error_log('Stripe API Error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => $this->getUserFriendlyMessage($e),
                'error_code' => $e->getStripeCode()
            ];
        } catch (\Exception $e) {
            error_log('Stripe Payment Error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.'
            ];
        }
    }

    private function getOrCreateCustomer($member, array $data): string
    {
        // Check if customer already exists in metadata
        if ($member->stripe_customer_id && is_string($member->stripe_customer_id)) {
            try {
                $customer = $this->stripe->customers->retrieve($member->stripe_customer_id);
                return $customer->id;
            } catch (ApiErrorException $e) {
                // Customer not found, create new one
            }
        }

        // Create new customer
        $customer = $this->stripe->customers->create([
            'email' => $member->email,
            'name' => $member->full_name,
            'metadata' => [
                'member_id' => $member->id,
                'site_id' => $member->site_id
            ]
        ]);

        // Store customer ID on member
        $member->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    private function createStripeSubscription(
        string           $customerId,
        SubscriptionPlan $plan,
        Subscription     $subscription
    ): \Stripe\Subscription
    {
        // Get or create price object
        $priceId = $this->getOrCreatePrice($plan);

        $subscriptionData = [
            'customer' => $customerId,
            'items' => [
                ['price' => $priceId]
            ],
            'metadata' => [
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'member_id' => $subscription->member_id
            ],
            'expand' => ['latest_invoice.payment_intent']
        ];

        // Add trial period if applicable
        if ($plan->trial_days > 0) {
            $subscriptionData['trial_period_days'] = $plan->trial_days;
        }

        return $this->stripe->subscriptions->create($subscriptionData);
    }

    private function getOrCreatePrice(SubscriptionPlan $plan): string
    {
        // Check if price already exists
        if (!empty($plan->stripe_price_id)) {
            return $plan->stripe_price_id;
        }

        // Create product first
        $product = $this->stripe->products->create([
            'name' => $plan->name,
            'description' => $plan->description,
            'metadata' => [
                'plan_id' => $plan->id
            ]
        ]);

        // Create price
        $price = $this->stripe->prices->create([
            'product' => $product->id,
            'unit_amount' => (int)($plan->price * 100), // Convert to cents
            'currency' => strtolower($plan->currency),
            'recurring' => [
                'interval' => $this->mapBillingPeriodToInterval($plan->billing_period)
            ],
            'metadata' => [
                'plan_id' => $plan->id
            ]
        ]);

        // Store price ID on plan
        $plan->update(['stripe_price_id' => $price->id]);

        return $price->id;
    }

    private function mapBillingPeriodToInterval(string $billingPeriod): string
    {
        return match ($billingPeriod) {
            'monthly' => 'month',
            'quarterly' => 'month', // Stripe doesn't have quarterly, use interval_count
            'yearly' => 'year',
            default => 'month'
        };
    }

    private function mapStripeStatus(string $status): string
    {
        return match ($status) {
            'active' => 'completed',
            'trialing' => 'completed',
            'incomplete' => 'processing',
            'incomplete_expired' => 'failed',
            'past_due' => 'failed',
            'canceled' => 'cancelled',
            'unpaid' => 'pending',
            default => 'pending'
        };
    }

    private function getUserFriendlyMessage(ApiErrorException $e): string
    {
        return match ($e->getStripeCode()) {
            'card_declined' => 'Your card was declined. Please try a different card.',
            'insufficient_funds' => 'Your card has insufficient funds.',
            'invalid_card_number' => 'The card number is invalid.',
            'invalid_expiry_month' => 'The expiration month is invalid.',
            'invalid_expiry_year' => 'The expiration year is invalid.',
            'invalid_cvc' => 'The card security code is invalid.',
            'expired_card' => 'Your card has expired.',
            default => 'Payment failed. Please check your card details and try again.'
        };
    }

    public function processOneTimePayment(array $orderData, array $paymentData): array
    {
        try {
            // If payment_method_id is provided (from Stripe.js), use it
            if (!empty($paymentData['payment_method_id'])) {
                $paymentIntent = $this->stripe->paymentIntents->create([
                    'amount' => (int)($orderData['amount'] * 100),
                    'currency' => strtolower($orderData['currency'] ?? 'usd'),
                    'payment_method' => $paymentData['payment_method_id'],
                    'confirm' => true,
                    'automatic_payment_methods' => [
                        'enabled' => true,
                        'allow_redirects' => 'never'
                    ],
                    'metadata' => [
                        'order_id' => $orderData['order_id'] ?? null
                    ]
                ]);
            } else {
                // Legacy support
                $paymentIntent = $this->stripe->paymentIntents->create([
                    'amount' => (int)($orderData['amount'] * 100),
                    'currency' => strtolower($orderData['currency'] ?? 'usd'),
                    'confirm' => true,
                    'metadata' => [
                        'order_id' => $orderData['order_id'] ?? null
                    ]
                ]);
            }

            return [
                'success' => true,
                'transaction_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'requires_action' => $paymentIntent->status === 'requires_action',
                'client_secret' => $paymentIntent->client_secret
            ];
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'message' => $this->getUserFriendlyMessage($e)
            ];
        }
    }

    public function cancelSubscription(string $stripeSubscriptionId): array
    {
        try {
            $subscription = $this->stripe->subscriptions->cancel($stripeSubscriptionId);

            return [
                'success' => true,
                'status' => $subscription->status
            ];
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function handleWebhook(array $payload, string $signature): array
    {
        $webhookSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? config('payment.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                json_encode($payload),
                $signature,
                $webhookSecret
            );

            return match ($event->type) {
                'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($event->data->object),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
                default => ['success' => true, 'message' => 'Event not handled']
            };
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function handleInvoicePaymentSucceeded($invoice): array
    {
        // Find payment by subscription ID
        $payment = Payment::where('metadata->stripe_subscription_id', $invoice->subscription)->first();

        if ($payment) {
            $this->paymentRepository->update($payment->id, [
                'status' => 'completed',
                'paid_at' => date('Y-m-d H:i:s'),
                'transaction_id' => $invoice->id
            ]);
        }

        return ['success' => true];
    }

    private function handleInvoicePaymentFailed($invoice): array
    {
        $payment = Payment::where('metadata->stripe_subscription_id', $invoice->subscription)->first();

        if ($payment) {
            $this->paymentRepository->update($payment->id, [
                'status' => 'failed',
                'failed_at' => date('Y-m-d H:i:s'),
                'error_message' => 'Payment failed'
            ]);
        }

        return ['success' => true];
    }

    private function handleSubscriptionUpdated($stripeSubscription): array
    {
        $subscription = Subscription::where('payment_subscription_id', $stripeSubscription->id)->first();

        if ($subscription) {
            $subscription->update([
                'status' => match ($stripeSubscription->status) {
                    'active', 'trialing' => 'active',
                    'past_due' => 'suspended',
                    'canceled', 'incomplete_expired' => 'cancelled',
                    default => 'pending'
                }
            ]);
        }

        return ['success' => true];
    }

    private function handleSubscriptionDeleted($stripeSubscription): array
    {
        $subscription = Subscription::where('payment_subscription_id', $stripeSubscription->id)->first();

        if ($subscription) {
            $subscription->update(['status' => 'cancelled']);
        }

        return ['success' => true];
    }

    public function createPaymentIntent(array $orderData): array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => (int)($orderData['amount'] * 100),
                'currency' => strtolower($orderData['currency'] ?? 'usd'),
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'metadata' => [
                    'order_id' => $orderData['order_id'] ?? null,
                    'site_id' => $orderData['site_id'] ?? null
                ]
            ]);

            return [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id
            ];
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'message' => $this->getUserFriendlyMessage($e)
            ];
        }
    }

    private function getPaymentIntentFromInvoice($invoiceId): ?string
    {
        try {
            if (is_string($invoiceId)) {
                $invoice = $this->stripe->invoices->retrieve($invoiceId);
            } else {
                $invoice = $invoiceId;
            }

            return $invoice->payment_intent ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}