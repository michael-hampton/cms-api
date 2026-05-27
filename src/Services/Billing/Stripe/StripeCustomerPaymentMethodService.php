<?php

namespace App\Services\Billing\Stripe;

use App\Models\Member;
use Exception;
use Stripe\StripeClient;

class StripeCustomerPaymentMethodService
{
    private StripeClient $stripe;

    public function __construct(?StripeClient $stripeClient = null)
    {
        if ($stripeClient) {
            $this->stripe = $stripeClient;
            return;
        }

        $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? config('payment.stripe.secret_key');
        $this->stripe = new StripeClient($secretKey);
    }

    public function getCustomerPaymentMethods(Member $member): array
    {
        if (!$member->stripe_customer_id) {
            return [
                'payment_methods' => [],
                'default_payment_method_id' => null,
            ];
        }

        try {
            $customer = $this->stripe->customers->retrieve($member->stripe_customer_id);
            $methods = $this->stripe->paymentMethods->all([
                'customer' => $member->stripe_customer_id,
                'type' => 'card',
            ]);

            return [
                'success' => true,
                'payment_methods' => $methods->data,
                'default_payment_method_id' => $customer->invoice_settings->default_payment_method,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'payment_methods' => [],
                'default_payment_method_id' => null,
                'message' => 'Failed to fetch payment methods',
            ];
        }
    }

    public function addPaymentMethod(Member $member, string $paymentMethodId, bool $setDefault = false): array
    {
        try {
            $customerId = $member->stripe_customer_id;

            if (!$customerId) {
                $customer = $this->stripe->customers->create([
                    'email' => $member->email,
                    'name' => $member->full_name,
                    'metadata' => [
                        'member_id' => $member->id,
                        'site_id' => $member->site_id,
                    ],
                ]);

                $member->update(['stripe_customer_id' => $customer->id]);
                $customerId = $customer->id;
            }

            $this->stripe->paymentMethods->attach($paymentMethodId, [
                'customer' => $customerId,
            ]);

            if ($setDefault) {
                $this->stripe->customers->update($customerId, [
                    'invoice_settings' => [
                        'default_payment_method' => $paymentMethodId,
                    ],
                ]);
            }

            return ['success' => true];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to add payment method: ' . $e->getMessage(),
            ];
        }
    }

    public function setDefaultPaymentMethod(string $customerId, string $paymentMethodId): array
    {
        try {
            $paymentMethod = $this->stripe->paymentMethods->retrieve($paymentMethodId);

            if (($paymentMethod->customer ?? null) !== $customerId) {
                return [
                    'success' => false,
                    'message' => 'Unauthorized',
                    'error_code' => 'unauthorized',
                ];
            }

            $this->stripe->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);

            return ['success' => true];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update default payment method',
            ];
        }
    }

    public function removePaymentMethod(Member $member, string $paymentMethodId): array
    {
        try {
            $paymentMethod = $this->stripe->paymentMethods->retrieve($paymentMethodId);

            if ($paymentMethod->customer !== $member->stripe_customer_id) {
                return [
                    'success' => false,
                    'message' => 'Unauthorized',
                    'error_code' => 'unauthorized',
                ];
            }

            $this->stripe->paymentMethods->detach($paymentMethodId);

            return ['success' => true];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to remove payment method',
            ];
        }
    }
}
