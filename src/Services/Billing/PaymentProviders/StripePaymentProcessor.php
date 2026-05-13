<?php

namespace App\Services\Billing\PaymentProviders;

use App\DTO\Payments\StripeSubscriptionResult;
use App\Enums\Vouchers\VoucherType;
use App\Framework\Support\Logger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Voucher;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Billing\RefundRepository;
use DateTime;
use Exception;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripePaymentProcessor
{
    private StripeClient $stripe;

    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly OrderRepository $orderRepository,
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

            $subscriber = !empty($subscription->gifted_by_member_id) ? $subscription->giftedBy : $subscription->member;
            // Create or retrieve customer
            $customerId = $this->getOrCreateCustomer($subscriber, $data);

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
                $subscription,
                false,
                $data
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

            return (new StripeSubscriptionResult(
                success: true,
                subscriptionId: $stripeSubscription->id,
                status: $stripeSubscription->status,
                customerId: $customerId,
                paymentIntentId: $paymentIntentId,
                requiresAction: $requiresAction,
                paymentIntentClientSecret: $clientSecret,
                currentPeriodStart: isset($stripeSubscription->current_period_start)
                    ? (int)$stripeSubscription->current_period_start
                    : null,
                currentPeriodEnd: isset($stripeSubscription->current_period_end)
                    ? (int)$stripeSubscription->current_period_end
                    : null,
            ))->toArray();

        } catch (ApiErrorException $e) {

            error_log('Stripe API Error: ' . $e->getMessage());

            echo $e->getMessage();
            die;

            return (new StripeSubscriptionResult(
                success: false,
                message: $this->getUserFriendlyMessage($e),
                errorCode: $e->getStripeCode()
            ))->toArray();
        } catch (Exception $e) {
            error_log('Stripe Payment Error: ' . $e->getMessage());

            return (new StripeSubscriptionResult(
                success: false,
                message: 'An unexpected error occurred. Please try again.'
            ))->toArray();
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

    public function createStripeSubscription(
        string           $customerId,
        SubscriptionPlan $plan,
        Subscription $subscription,
        bool         $fromPaymentIntentFlow = false
    ): \Stripe\Subscription
    {
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

            'expand' => ['latest_invoice.payment_intent'],
            'collection_method' => 'charge_automatically',
        ];

        if ($plan->trial_days > 0) {
            $subscriptionData['trial_period_days'] = $plan->trial_days;
        }

        return $this->stripe->subscriptions->create($subscriptionData);
    }

    private function resolveSubscriptionAmountCents(Subscription $subscription, array $data = []): int
    {
        if (isset($data['amount_cents'])) {
            return (int)$data['amount_cents'];
        }

        if (isset($data['amount'])) {
            return (int)round(((float)$data['amount']) * 100);
        }

        return $subscription->price_paid_cents
            ?? (int)round(((float)$subscription->price) * 100);
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

    public function cancelSubscription(string $stripeSubscriptionId, bool $cancelAtPeriodEnd = true): array
    {
        try {
            $updateData = [];

            if ($cancelAtPeriodEnd) {
                // Cancel at period end - subscription remains active until then
                $subscription = $this->stripe->subscriptions->update($stripeSubscriptionId, [
                    'cancel_at_period_end' => true
                ]);
            } else {
                // Cancel immediately
                $subscription = $this->stripe->subscriptions->cancel($stripeSubscriptionId);
            }

            return [
                'success' => true,
                'status' => $subscription->status,
                'cancel_at_period_end' => $subscription->cancel_at_period_end ?? false,
                'canceled_at' => $subscription->canceled_at ?? null,
                'current_period_end' => $subscription->current_period_end ?? null
            ];
        } catch (ApiErrorException $e) {
            error_log('Stripe cancel subscription error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ];
        }
    }

    /**
     * Reactivate a cancelled subscription (only works if cancel_at_period_end is true)
     */
    public function reactivateSubscription(string $stripeSubscriptionId): array
    {
        try {
            // First check the subscription status
            $subscription = $this->stripe->subscriptions->retrieve($stripeSubscriptionId);

            // Can only reactivate if it's set to cancel at period end but hasn't been canceled yet
            if ($subscription->status === 'canceled') {
                return [
                    'success' => false,
                    'message' => 'Subscription has already been canceled and cannot be reactivated. Please create a new subscription.',
                    'error_code' => 'subscription_already_canceled'
                ];
            }

            // Check if it's set to cancel at period end
            if (!$subscription->cancel_at_period_end) {
                return [
                    'success' => false,
                    'message' => 'Subscription is not scheduled for cancellation',
                    'error_code' => 'subscription_not_scheduled_for_cancellation'
                ];
            }

            // Remove the cancel_at_period_end flag to reactivate
            $subscription = $this->stripe->subscriptions->update($stripeSubscriptionId, [
                'cancel_at_period_end' => false
            ]);

            return [
                'success' => true,
                'status' => $subscription->status,
                'cancel_at_period_end' => false
            ];
        } catch (ApiErrorException $e) {
            error_log('Stripe reactivate subscription error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ];
        }
    }

    public function createRefund(string $paymentIntentId, array $options = []): array
    {
        try {
            $refundData = [
                'payment_intent' => $paymentIntentId
            ];

            if (isset($options['amount'])) {
                $refundData['amount'] = (int)($options['amount'] * 100); // Convert to cents
            }

            if (isset($options['reason'])) {
                $refundData['reason'] = $options['reason']; // 'duplicate', 'fraudulent', 'requested_by_customer'
            }

            if (isset($options['metadata'])) {
                $refundData['metadata'] = $options['metadata'];
            }

            $refund = $this->stripe->refunds->create($refundData);

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'amount' => $refund->amount / 100, // Convert back to dollars
                'status' => $refund->status,
                'created' => $refund->created
            ];
        } catch (ApiErrorException $e) {
            error_log('Stripe create refund error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ];
        }
    }

    public function getSubscription(string $stripeSubscriptionId): array
    {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($stripeSubscriptionId, [
                'expand' => ['latest_invoice.payment_intent']
            ]);

            return [
                'success' => true,
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'current_period_start' => $subscription->current_period_start,
                    'current_period_end' => $subscription->current_period_end,
                    'cancel_at_period_end' => $subscription->cancel_at_period_end,
                    'canceled_at' => $subscription->canceled_at,
                    'ended_at' => $subscription->ended_at
                ]
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
            $event = Webhook::constructEvent(
                json_encode($payload),
                $signature,
                $webhookSecret
            );

            return match ($event->type) {
                'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($event->data->object),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
                'charge.refunded' => $this->handleChargeRefunded($event->data->object),
                default => ['success' => true, 'message' => 'Event not handled']
            };
        } catch (Exception $e) {
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
                },
                'current_period_start' => date('Y-m-d H:i:s', $stripeSubscription->current_period_start),
                'current_period_end' => date('Y-m-d H:i:s', $stripeSubscription->current_period_end)
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

    private function handleChargeRefunded($charge): array
    {
        try {
            // Find payment by payment_intent_id
            $payment = Payment::where('payment_intent_id', $charge->payment_intent)->first();

            if ($payment) {
                $refundAmount = $charge->amount_refunded / 100; // Convert from cents

                $this->paymentRepository->update($payment->id, [
                    'status' => 'refunded',
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'refund_amount' => $refundAmount,
                        'refunded_at' => date('Y-m-d H:i:s'),
                        'stripe_refund_id' => $charge->id
                    ])
                ]);

                // Create a negative payment record for the refund
                $this->paymentRepository->create([
                    'subscription_id' => $payment->subscription_id,
                    'order_id' => $payment->order_id,
                    'site_id' => $payment->site_id,
                    'payment_method' => 'stripe',
                    'payment_provider' => 'stripe',
                    'amount' => -$refundAmount,
                    'currency' => strtoupper($charge->currency),
                    'status' => 'completed',
                    'paid_at' => date('Y-m-d H:i:s'),
                    'transaction_id' => $charge->id,
                    'metadata' => [
                        'refund' => true,
                        'original_payment_id' => $payment->id,
                        'stripe_refund_id' => $charge->id
                    ]
                ]);
            }

            return ['success' => true];
        } catch (Exception $e) {
            Logger::error('Failed to handle charge refunded webhook', [
                'charge_id' => $charge->id,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Process a refund for a payment
     *
     * @param string $transactionId The Stripe charge or payment intent ID
     * @param float $amount The amount to refund (in dollars)
     * @param array $options Additional options (reason, metadata, etc.)
     * @return array Result with success status, refund_id, and message
     */
    public function refund(string $transactionId, float $amount, array $options = []): array
    {
        try {
            // Convert amount to cents for Stripe
            $amountInCents = (int)round($amount * 100);

            $refundParams = [
                'amount' => $amountInCents,
            ];

            // Add reason if provided
            if (!empty($options['reason'])) {
                $refundParams['reason'] = $options['reason'];
            }

            // Add metadata if provided
            if (!empty($options['metadata'])) {
                $refundParams['metadata'] = $options['metadata'];
            }

            // Determine if this is a charge ID or payment intent ID
            // Charge IDs start with 'ch_', payment intent IDs start with 'pi_'
            if (str_starts_with($transactionId, 'pi_')) {
                $refundParams['payment_intent'] = $transactionId;
            } else {
                $refundParams['charge'] = $transactionId;
            }

            $refund = $this->stripe->refunds->create($refundParams);

            Logger::info('Stripe refund processed', [
                'refund_id' => $refund->id,
                'amount' => $amount,
                'transaction_id' => $transactionId,
                'status' => $refund->status
            ]);

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'amount' => $amount,
                'status' => $refund->status,
                'message' => 'Refund processed successfully'
            ];

        } catch (ApiErrorException $e) {
            Logger::error('Stripe refund failed', [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode()
            ]);

            return [
                'success' => false,
                'message' => $this->getUserFriendlyMessage($e),
                'error_code' => $e->getStripeCode()
            ];

        } catch (Exception $e) {
            Logger::error('Refund processing error', [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred while processing the refund'
            ];
        }
    }

    public function paymentMethodBelongsToCustomer(string $stripe_customer_id, string $paymentMethodId)
    {
        return false;
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
                    'site_id' => $orderData['site_id'] ?? null,
                    'subscription_id' => $orderData['subscription_id'] ?? null,
                ]
            ]);

            return [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id
            ];
        } catch (ApiErrorException $e) {
            echo $e->getMessage();
            die;
            return [
                'success' => false,
                'message' => $this->getUserFriendlyMessage($e)
            ];
        }
    }

    /**
     * Create payment intent with customer for one-time subscriptions
     * This ensures payment methods are saved for future use
     */
    public function createPaymentIntentWithCustomer(array $orderData): array
    {
        try {
            $customerId = null;

            // Get or create customer if member provided
            if (isset($orderData['member'])) {
                $customerId = $this->getOrCreateCustomer($orderData['member'], []);
            }

            $paymentIntentData = [
                'amount' => (int)($orderData['amount'] * 100),
                'currency' => strtolower($orderData['currency'] ?? 'usd'),
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'metadata' => array_merge(
                    $orderData['metadata'] ?? [],
                    [
                        'order_id' => $orderData['order_id'] ?? null,
                        'site_id' => $orderData['site_id'] ?? null,
                        'subscription_id' => $orderData['subscription_id'] ?? null,
                    ]
                ),
            ];

            // Add customer and setup for future usage
            if ($customerId) {
                $paymentIntentData['customer'] = $customerId;
                $paymentIntentData['setup_future_usage'] = 'off_session';
            }

            $paymentIntent = $this->stripe->paymentIntents->create($paymentIntentData);

            $result = [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
            ];

            // Only include customer_id if we have one
            if ($customerId) {
                $result['customer_id'] = $customerId;
            }

            return $result;
        } catch (ApiErrorException $e) {
            error_log('Stripe Payment Intent Error: ' . $e->getMessage());

            echo $e->getMessage();
            die;

            return [
                'success' => false,
                'message' => $this->getUserFriendlyMessage($e)
            ];
        }
    }

    /**
     * Add payment method to customer
     */
    public function addPaymentMethod($member, string $paymentMethodId, bool $setDefault = false): array
    {
        try {
            $customerId = $member->stripe_customer_id;
            // Create customer if doesn't exist
            if (!$customerId) {
                $customer = $this->stripe->customers->create([
                    'email' => $member->email,
                    'name' => $member->full_name,
                    'metadata' => [
                        'member_id' => $member->id,
                        'site_id' => $member->site_id
                    ]
                ]);

                $member->update(['stripe_customer_id' => $customer->id]);
                $customerId = $customer->id;
            }

            // Attach payment method to customer
            $this->stripe->paymentMethods->attach($paymentMethodId, [
                'customer' => $customerId
            ]);

            // Set as default if requested
            if ($setDefault) {
                $this->stripe->customers->update($member->stripe_customer_id, [
                    'invoice_settings' => [
                        'default_payment_method' => $paymentMethodId
                    ]
                ]);
            }

            return ['success' => true];
        } catch (Exception $e) {
            error_log('Error adding payment method: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to add payment method: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Set default payment method for customer
     */
    public function setDefaultPaymentMethod(string $customerId, string $paymentMethodId): array
    {
        try {
            $this->stripe->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId
                ]
            ]);

            return ['success' => true];
        } catch (Exception $e) {
            error_log('Error setting default payment method: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to update default payment method'
            ];
        }
    }

    /**
     * Remove payment method
     */
    public function removePaymentMethod($member, string $paymentMethodId): array
    {
        try {
            // Verify payment method belongs to customer
            $paymentMethod = $this->stripe->paymentMethods->retrieve($paymentMethodId);

            if ($paymentMethod->customer !== $member->stripe_customer_id) {
                return [
                    'success' => false,
                    'message' => 'Unauthorized',
                    'error_code' => 'unauthorized'
                ];
            }

            // Detach payment method
            $this->stripe->paymentMethods->detach($paymentMethodId);

            return ['success' => true];
        } catch (Exception $e) {
            error_log('Error removing payment method: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to remove payment method'
            ];
        }
    }

    public function processSubscriptionPaymentWithVoucher(
        Subscription     $subscription,
        SubscriptionPlan $plan,
        ?Voucher         $voucher,
        array            $data
    ): array
    {
        try {
            $customerId = $this->getOrCreateCustomer($subscription->member, $data);

            if (!empty($data['payment_method_id'])) {
                $paymentMethodId = $data['payment_method_id'];

                $this->stripe->paymentMethods->attach($paymentMethodId, [
                    'customer' => $customerId
                ]);

                $this->stripe->customers->update($customerId, [
                    'invoice_settings' => [
                        'default_payment_method' => $paymentMethodId
                    ]
                ]);
            }

            // Create or get Stripe coupon for voucher
            $couponId = null;
            if ($voucher && $voucher->appliesToSubscriptions()) {
                $couponId = $this->getOrCreateStripeCoupon($voucher, $plan);
            }

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

            // Apply coupon if voucher provided
            if ($couponId) {
                $subscriptionData['discounts'] = [
                    ['coupon' => $couponId]
                ];
                $subscriptionData['metadata']['voucher_id'] = $voucher->id;
                $subscriptionData['metadata']['voucher_code'] = $voucher->code;
            }

            if ($plan->trial_days > 0) {
                $subscriptionData['trial_period_days'] = $plan->trial_days;
            }

            $stripeSubscription = $this->stripe->subscriptions->create($subscriptionData);

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

            // Calculate actual amount after discount
            $actualAmount = $subscription->getDiscountedPrice();

            $payment = $this->paymentRepository->create([
                'subscription_id' => $subscription->id,
                'site_id' => $subscription->site_id,
                'payment_method' => 'stripe',
                'payment_provider' => 'stripe',
                'transaction_id' => is_string($latestInvoice) ? $latestInvoice : $latestInvoice->id,
                'payment_intent_id' => $paymentIntentId,
                'status' => $this->mapStripeStatus($stripeSubscription->status),
                'amount' => $actualAmount,
                'currency' => strtoupper($plan->currency),
                'metadata' => [
                    'subscription_id' => $subscription->id,
                    'plan_id' => $plan->id,
                    'billing_period' => $plan->billing_period,
                    'stripe_subscription_id' => $stripeSubscription->id,
                    'stripe_customer_id' => $customerId,
                    'voucher_id' => $voucher ? $voucher->id : null,
                    'voucher_code' => $voucher ? $voucher->code : null,
                    'discount_amount' => $subscription->discount_amount,
                    'original_amount' => $subscription->original_price
                ]
            ]);

            if ($stripeSubscription->status === 'active' && !$requiresAction) {
                $this->paymentRepository->update($payment->id, [
                    'status' => 'completed',
                    'paid_at' => date('Y-m-d H:i:s')
                ]);

                $subscription->update([
                    'status' => 'active',
                    'current_period_start' => date('Y-m-d H:i:s', $stripeSubscription->current_period_start),
                    'current_period_end' => date('Y-m-d H:i:s', $stripeSubscription->current_period_end)
                ]);
            }

            return [
                'success' => true,
                'payment_intent_id' => $paymentIntentId,
                'subscription_id' => $stripeSubscription->id,
                'status' => $stripeSubscription->status,
                'customer_id' => $customerId,
                'requires_action' => $requiresAction,
                'payment_intent_client_secret' => $clientSecret,
                'discount_applied' => $subscription->discount_amount > 0
            ];

        } catch (ApiErrorException $e) {
            error_log('Stripe API Error: ' . $e->getMessage());

            echo $e->getMessage();
            die;

            return [
                'success' => false,
                'message' => $this->getUserFriendlyMessage($e),
                'error_code' => $e->getStripeCode()
            ];
        } catch (Exception $e) {
            echo $e->getMessage();
            die;
            error_log('Stripe Payment Error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.'
            ];
        }
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

    private function getOrCreateStripeCoupon(Voucher $voucher, SubscriptionPlan $plan): string
    {
        // Check if coupon already exists
        if ($voucher->stripe_coupon_id) {
            try {
                $this->stripe->coupons->retrieve($voucher->stripe_coupon_id);
                return $voucher->stripe_coupon_id;
            } catch (Exception $e) {
                // Coupon doesn't exist, create new one
            }
        }

        $couponData = [
            'name' => $voucher->name,
            'metadata' => [
                'voucher_id' => $voucher->id,
                'voucher_code' => $voucher->code
            ]
        ];

        if ($voucher->type === VoucherType::Percentage->value) {
            $couponData['percent_off'] = (int)$voucher->value;
        } else {
            $couponData['amount_off'] = (int)($voucher->value * 100); // Convert to cents
            $couponData['currency'] = $plan->currency; // Should match subscription currency
        }

        // Set duration
        if ($voucher->duration_in_months) {
            $couponData['duration'] = 'repeating';
            $couponData['duration_in_months'] = $voucher->duration_in_months;
        } else {
            $couponData['duration'] = 'once'; // Apply only to first payment
        }

        if ($voucher->maximum_discount && $voucher->type === VoucherType::Percentage->value) {
            $couponData['max_redemptions'] = 1; // Stripe doesn't support max discount per transaction
        }

        $stripeCoupon = $this->stripe->coupons->create($couponData);

        // Store coupon ID
        $voucher->update(['stripe_coupon_id' => $stripeCoupon->id]);

        return $stripeCoupon->id;
    }

    public function confirmPaymentIntent(string $paymentIntentId): array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            return [
                'success' => true,
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount / 100,
                'currency' => $paymentIntent->currency,
            ];
        } catch (ApiErrorException $e) {

            echo $e->getMessage();
            die;

            return [
                'success' => false,
                'message' => $this->getUserFriendlyMessage($e),
                'error_code' => $e->getStripeCode()
            ];
        }
    }

    public function handleOneTimeSubscriptionPayment(
        string $paymentIntentId,
        int    $orderId,
        int $siteId,
            $subscriptionIds = null, // Can be single ID or array of IDs
    ): array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            if ($paymentIntent->status !== 'succeeded') {
                return [
                    'success' => false,
                    'message' => 'Payment not completed'
                ];
            }

            // Normalize to array
            if (!is_array($subscriptionIds)) {
                $subscriptionIds = $subscriptionIds ? [$subscriptionIds] : [];
            }

            // If payment intent has a customer and payment method, ensure it's attached and set as default
            if ($paymentIntent->customer && $paymentIntent->payment_method) {
                try {
                    // Retrieve payment method to check if it's already attached
                    $paymentMethod = $this->stripe->paymentMethods->retrieve($paymentIntent->payment_method);

                    // If not attached to this customer, attach it
                    if ($paymentMethod->customer !== $paymentIntent->customer) {
                        $this->stripe->paymentMethods->attach($paymentIntent->payment_method, [
                            'customer' => $paymentIntent->customer
                        ]);
                    }

                    // Set as default payment method
                    $this->stripe->customers->update($paymentIntent->customer, [
                        'invoice_settings' => [
                            'default_payment_method' => $paymentIntent->payment_method
                        ]
                    ]);
                } catch (Exception $e) {
                    // Log but don't fail the payment if saving payment method fails
                    error_log('Failed to save payment method: ' . $e->getMessage());
                }
            }

            // Create payment record
            $payment = $this->paymentRepository->create([
                'order_id' => $orderId,
                'subscription_id' => count($subscriptionIds) === 1 ? $subscriptionIds[0] : null,
                'site_id' => $siteId,
                'payment_method' => 'stripe',
                'payment_provider' => 'stripe',
                'transaction_id' => $paymentIntent->id,
                'payment_intent_id' => $paymentIntent->id,
                'status' => 'completed',
                'amount' => $paymentIntent->amount / 100,
                'currency' => strtoupper($paymentIntent->currency),
                'paid_at' => date('Y-m-d H:i:s'),
                'metadata' => [
                    'subscription_ids' => $subscriptionIds,
                    'order_id' => $orderId,
                    'one_time_subscription' => true,
                    'multiple_subscriptions' => count($subscriptionIds) > 1,
                    'stripe_customer_id' => $paymentIntent->customer ?? null,
                    'payment_method_saved' => !empty($paymentIntent->customer)
                ]
            ]);

            $this->orderRepository->update($orderId, [
                'status' => 'completed',
                'payment_status' => 'paid'
            ]);

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'transaction_id' => $paymentIntent->id,
            ];

        } catch (ApiErrorException $e) {
            echo $e->getMessage();
            die;
            return [
                'success' => false,
                'message' => $this->getUserFriendlyMessage($e),
                'error_code' => $e->getStripeCode()
            ];
        }
    }

    /**
     * Update customer email in Stripe
     */
    public function updateCustomerEmail(string $customerId, string $newEmail): array
    {
        try {
            $customer = $this->stripe->customers->update($customerId, [
                'email' => $newEmail
            ]);

            return [
                'success' => true,
                'customer_id' => $customer->id,
                'email' => $customer->email
            ];
        } catch (Exception $e) {
            error_log('Error updating Stripe customer email: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to update customer email in Stripe'
            ];
        }
    }

    /**
     * Get payment methods with expiry warnings
     */
    public function getPaymentMethodsWithWarnings($member, array $customerPaymentMethods = []): array
    {
        if (empty($customerPaymentMethods)) {
            $customerPaymentMethods = $this->getCustomerPaymentMethods($member);
        }

        if (!$customerPaymentMethods['success']) {
            return $customerPaymentMethods;
        }

        $warnings = [];
        foreach ($customerPaymentMethods['payment_methods'] as $method) {
            if ($this->isPaymentMethodExpired($method)) {
                $warnings[] = [
                    'payment_method' => $method,
                    'status' => 'expired',
                    'message' => 'This card has expired and needs to be updated'
                ];
            } elseif ($this->isPaymentMethodExpiring($method)) {
                $warnings[] = [
                    'payment_method' => $method,
                    'status' => 'expiring',
                    'message' => 'This card expires soon (' . $method->card->exp_month . '/' . $method->card->exp_year . ')'
                ];
            }
        }

        $result['warnings'] = $warnings;
        $result['success'] = count($warnings) === 0;
        $result['has_warnings'] = !empty($warnings);

        return $result;
    }

    /**
     * Get customer's payment methods
     */
    public function getCustomerPaymentMethods($member): array
    {
        $paymentMethods = [];
        $defaultPaymentMethodId = null;

        if (!$member->stripe_customer_id) {
            return [
                'payment_methods' => [],
                'default_payment_method_id' => null
            ];
        }

        try {
            $customer = $this->stripe->customers->retrieve($member->stripe_customer_id);
            $defaultPaymentMethodId = $customer->invoice_settings->default_payment_method;

            $methods = $this->stripe->paymentMethods->all([
                'customer' => $member->stripe_customer_id,
                'type' => 'card',
            ]);

            $paymentMethods = $methods->data;

            return [
                'success' => true,
                'payment_methods' => $paymentMethods,
                'default_payment_method_id' => $defaultPaymentMethodId
            ];
        } catch (Exception $e) {
            error_log('Error fetching payment methods: ' . $e->getMessage());

            return [
                'success' => false,
                'payment_methods' => [],
                'default_payment_method_id' => null,
                'message' => 'Failed to fetch payment methods'
            ];
        }
    }

    /**
     * Check if payment method is expired
     */
    public function isPaymentMethodExpired($paymentMethod): bool
    {
        if (!isset($paymentMethod->card)) {
            return false;
        }

        $card = $paymentMethod->card;
        $expiryDate = new DateTime("{$card->exp_year}-{$card->exp_month}-01");
        $expiryDate->modify('last day of this month');

        return $expiryDate < new DateTime();
    }

    /**
     * Check if payment method is expiring soon
     */
    public function isPaymentMethodExpiring($paymentMethod, int $monthsThreshold = 2): bool
    {
        if (!isset($paymentMethod->card)) {
            return false;
        }

        $card = $paymentMethod->card;
        $expiryDate = new DateTime("{$card->exp_year}-{$card->exp_month}-01");
        $expiryDate->modify('last day of this month');

        $now = new DateTime();
        $threshold = (clone $now)->modify("+{$monthsThreshold} months");

        return $expiryDate <= $threshold && $expiryDate >= $now;
    }

    /**
     * Update subscription billing cycle anchor (payment date)
     * Uses Stripe Subscription Schedules API for proper billing date changes
     */
    public function updateBillingCycleAnchor(
        string $stripeSubscriptionId,
        int  $dayOfMonth,
        bool $prorate = true
    ): array
    {
        try {
            // Get current subscription (expand schedule just in case)
            $subscription = $this->stripe->subscriptions->retrieve($stripeSubscriptionId, [
                'expand' => ['schedule']
            ]);

            if ($subscription->status === 'canceled') {
                return [
                    'success' => false,
                    'message' => 'Cannot update billing date for cancelled subscription'
                ];
            }

            // Calculate next target billing date
            $now = new DateTime();

            $targetDate = new DateTime();
            $targetDate->setDate(
                (int)$targetDate->format('Y'),
                (int)$targetDate->format('m'),
                min($dayOfMonth, (int)$targetDate->format('t'))
            );

            if ($targetDate <= $now) {
                $targetDate->modify('+1 month');
                $targetDate->setDate(
                    (int)$targetDate->format('Y'),
                    (int)$targetDate->format('m'),
                    min($dayOfMonth, (int)$targetDate->format('t'))
                );
            }

            // Get or create schedule (ONLY valid approach)
            $schedule = null;

            if (!empty($subscription->schedule)) {
                $scheduleId = is_string($subscription->schedule)
                    ? $subscription->schedule
                    : $subscription->schedule->id;

                $schedule = $this->stripe->subscriptionSchedules->retrieve($scheduleId);
            }

            // Create schedule if it doesn't exist
            if (!$schedule) {
                $schedule = $this->stripe->subscriptionSchedules->create([
                    'from_subscription' => $stripeSubscriptionId,
                ]);
            }

            // Build subscription items snapshot
            $items = [];
            foreach ($subscription->items->data as $item) {
                $items[] = [
                    'price' => $item->price->id,
                    'quantity' => $item->quantity ?? 1,
                ];
            }

            // Update schedule with new phases
            $updatedSchedule = $this->stripe->subscriptionSchedules->update(
                $schedule->id,
                [
                    'end_behavior' => 'release',
                    'phases' => [
                        [
                            'items' => $items,
                            'start_date' => (int)$subscription->current_period_start,
                            'end_date' => $targetDate->getTimestamp(),
                            'proration_behavior' => $prorate ? 'create_prorations' : 'none',
                        ],
                        [
                            'items' => $items,
                            'proration_behavior' => $prorate ? 'create_prorations' : 'none',
                        ],
                    ],
                ]
            );

            // Release schedule so it applies immediately
            $releasedSchedule = $this->stripe->subscriptionSchedules->release($updatedSchedule->id);

            return [
                'success' => true,
                'subscription' => $releasedSchedule->subscription,
                'schedule_id' => $releasedSchedule->id,
                'new_billing_date' => $targetDate->format('Y-m-d'),
                'message' => 'Billing date updated successfully'
            ];

        } catch (ApiErrorException $e) {
            error_log('Stripe billing date update error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ];
        } catch (Exception $e) {
            error_log('Billing date update error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'An error occurred while updating billing date'
            ];
        }
    }

    /**
     * Calculate proration amount for billing date change
     */
    public function calculateBillingDateProration(
        string $stripeSubscriptionId,
        int    $newDayOfMonth
    ): array
    {
        try {

            $subscription = $this->stripe->subscriptions->retrieve($stripeSubscriptionId);

            $periodStart = $subscription->items->data[0]->current_period_start;
            $periodEnd = $subscription->items->data[0]->current_period_end;

            $now = new DateTime();
            $currentPeriodEnd = new DateTime();
            $currentPeriodEnd->setTimestamp($periodEnd);

            // Calculate target date
            $targetDate = new DateTime();
            $targetDate->setDate(
                $targetDate->format('Y'),
                $targetDate->format('m'),
                min($newDayOfMonth, (int)$targetDate->format('t'))
            );

            if ($targetDate <= $now) {
                $targetDate->modify('+1 month');
                $targetDate->setDate(
                    $targetDate->format('Y'),
                    $targetDate->format('m'),
                    min($newDayOfMonth, (int)$targetDate->format('t'))
                );
            }

            // Calculate days difference
            $interval = $now->diff($targetDate);
            $daysToNewDate = $interval->days;

            $currentInterval = $now->diff($currentPeriodEnd);
            $daysInCurrentPeriod = $currentInterval->days;

            // Get subscription price
            $amount = $subscription->items->data[0]->price->unit_amount / 100;

            // Simple proration calculation
            $dailyRate = $amount / 30; // Simplified daily rate

            if ($daysToNewDate < $daysInCurrentPeriod) {
                // Moving date earlier - credit
                $prorationAmount = -($dailyRate * ($daysInCurrentPeriod - $daysToNewDate));
            } else {
                // Moving date later - charge
                $prorationAmount = $dailyRate * ($daysToNewDate - $daysInCurrentPeriod);
            }

            return [
                'success' => true,
                'current_period_end' => $currentPeriodEnd->format('Y-m-d'),
                'new_billing_date' => $targetDate->format('Y-m-d'),
                'proration_amount' => round($prorationAmount, 2),
                'is_credit' => $prorationAmount < 0,
                'days_difference' => $daysToNewDate - $daysInCurrentPeriod
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function updateSubscriptionPlan(
        string $stripeSubscriptionId,
        string $newPriceId,
        array  $metadata = []
    ): array
    {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($stripeSubscriptionId);

            $this->stripe->subscriptions->update($stripeSubscriptionId, [
                'items' => [
                    [
                        'id' => $subscription->items->data[0]->id,
                        'price' => $newPriceId,
                    ]
                ],
                'proration_behavior' => 'always_invoice',
                'metadata' => $metadata,
            ]);

            return [
                'success' => true,
                'stripe_subscription_id' => $stripeSubscriptionId,
            ];
        } catch (Exception $e) {
            Logger::error("Failed to update Stripe subscription", [
                'stripe_subscription_id' => $stripeSubscriptionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Charge a customer's default saved payment method off-session.
     *
     * Used by TrialConversionService to collect the first real payment after
     * a free trial ends.  The customer and their default payment method must
     * already exist in Stripe (set up during the initial checkout via
     * createPaymentIntentWithCustomer with setup_future_usage = off_session).
     *
     * Returns the same shape as createPaymentIntent() so callers can treat
     * the result uniformly.
     *
     * ADD THIS METHOD to StripePaymentProcessor, before the closing brace.
     */
    public function chargeOffSession(
        string $stripeCustomerId,
        int    $amountCents,
        string $currency,
        array  $metadata = []
    ): array
    {
        try {
            // Retrieve the customer's default payment method
            $customer = $this->stripe->customers->retrieve($stripeCustomerId, [
                'expand' => ['invoice_settings.default_payment_method'],
            ]);

            $defaultPaymentMethod = $customer->invoice_settings->default_payment_method ?? null;

            if (!$defaultPaymentMethod) {
                return [
                    'success' => false,
                    'message' => 'No default payment method on file for this customer.',
                ];
            }

            $paymentMethodId = is_string($defaultPaymentMethod)
                ? $defaultPaymentMethod
                : $defaultPaymentMethod->id;

            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $amountCents,
                'currency' => strtolower($currency),
                'customer' => $stripeCustomerId,
                'payment_method' => $paymentMethodId,
                'confirm' => true,
                'off_session' => true,
                'metadata' => $metadata,
            ]);

            return [
                'success' => true,
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
                'status' => $paymentIntent->status,
            ];

        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'message' => $this->getUserFriendlyMessage($e),
                'error_code' => $e->getStripeCode(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'An unexpected error occurred during off-session charge.',
            ];
        }
    }

    /**
     * Create a refund record in the refunds table
     */
    private function createRefundRecord(Payment $payment, float $refundAmount, string $stripeRefundId): void
    {
        $refundRepo = new RefundRepository();

        // Check if refund already exists for this charge
        $existingRefund = Refund::where('site_id', $payment->site_id)
            ->where('order_id', $payment->order_id)
            ->whereRaw("JSON_EXTRACT(internal_notes, '$.stripe_refund_id') = ?", [$stripeRefundId])
            ->first();

        if ($existingRefund) {
            return; // Already processed
        }

        $order = Order::find($payment->order_id);
        if (!$order) {
            return;
        }

        // Create refund record
        $refundData = [
            'order_id' => $payment->order_id,
            'site_id' => $payment->site_id,
            'refund_type' => 'full', // Can be 'partial' if amount is less than order total
            'refund_amount' => $refundAmount,
            'reason' => 'Stripe refund processed',
            'internal_notes' => json_encode([
                'stripe_refund_id' => $stripeRefundId,
                'payment_id' => $payment->id,
                'processed_via_webhook' => true,
                'processed_at' => date('Y-m-d H:i:s')
            ]),
            'notify_customer' => false, // Already notified by Stripe
            'restock_items' => false, // Manual decision needed
            'status' => 'processed',
            'processed_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Determine if full or partial refund
        if ($refundAmount < $order->total) {
            $refundData['refund_type'] = 'partial';
        }

        $refund = $refundRepo->create($refundData);

        // Create refund items based on order items
        $orderItems = OrderItem::where('order_id', $order->id)->get();

        foreach ($orderItems as $orderItem) {
            // Calculate proportional refund for each item
            $itemRefundAmount = ($orderItem->price * $orderItem->quantity / $order->total) * $refundAmount;

            $refundRepo->createRefundItem([
                'refund_id' => $refund->id,
                'order_item_id' => $orderItem->id,
                'product_id' => $orderItem->product_id,
                'product_name' => $orderItem->product_name,
                'quantity' => $orderItem->quantity,
                'refund_quantity' => $orderItem->quantity, // Full refund of item
                'unit_price' => $orderItem->price,
                'refund_amount' => $itemRefundAmount,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        // Update order status
        $totalRefunded = $refundRepo->getTotalRefundedAmount($order->id);
        $orderStatus = $totalRefunded >= $order->total ? 'refunded' : 'partially_refunded';

        Order::where('id', $order->id)->update([
            'status' => $orderStatus,
            'payment_status' => 'refunded'
        ]);
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
        } catch (Exception $e) {
            return null;
        }
    }
}
