<?php

namespace App\Services\Billing\Payments;

use App\Models\Member;
use Stripe\StripeClient;

class SavedPaymentMethodService
{
    private StripeClient $stripe;

    public function __construct(
        ?StripeClient $stripeClient = null
    )
    {
        if ($stripeClient) {
            $this->stripe = $stripeClient;
        } else {
            $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? config('payment.stripe.secret_key');
            $this->stripe = new StripeClient($secretKey);
        }
    }

    /**
     * Get member's saved payment methods from Stripe
     */
    public function getMemberPaymentMethods(Member $member): array
    {
        if (!$member->stripe_customer_id) {
            return [];
        }

        try {
            $paymentMethods = $this->stripe->paymentMethods->all([
                'customer' => $member->stripe_customer_id,
                'type' => 'card'
            ]);

            return array_map(function ($pm) {
                return [
                    'id' => $pm->id,
                    'type' => $pm->type,
                    'card' => [
                        'brand' => $pm->card->brand,
                        'last4' => $pm->card->last4,
                        'exp_month' => $pm->card->exp_month,
                        'exp_year' => $pm->card->exp_year,
                        'funding' => $pm->card->funding ?? null
                    ],
                    'billing_details' => [
                        'name' => $pm->billing_details->name ?? null,
                        'email' => $pm->billing_details->email ?? null
                    ],
                    'created' => date('Y-m-d H:i:s', $pm->created)
                ];
            }, $paymentMethods->data);
        } catch (\Exception $e) {
            echo $e->getMessage();
            die('mike');
            // Log error but don't throw - just return empty array
            error_log("Failed to fetch payment methods for member {$member->id}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get default payment method for customer
     */
    public function getDefaultPaymentMethod(Member $member): ?array
    {
        if (!$member->stripe_customer_id) {
            return null;
        }

        try {
            $customer = $this->stripe->customers->retrieve($member->stripe_customer_id);

            if (!$customer->invoice_settings->default_payment_method) {
                return null;
            }

            $pm = $this->stripe->paymentMethods->retrieve(
                $customer->invoice_settings->default_payment_method
            );

            return [
                'id' => $pm->id,
                'type' => $pm->type,
                'card' => [
                    'brand' => $pm->card->brand,
                    'last4' => $pm->card->last4,
                    'exp_month' => $pm->card->exp_month,
                    'exp_year' => $pm->card->exp_year,
                    'funding' => $pm->card->funding ?? null
                ],
                'billing_details' => [
                    'name' => $pm->billing_details->name ?? null,
                    'email' => $pm->billing_details->email ?? null
                ],
                'is_default' => true
            ];
        } catch (\Exception $e) {
            error_log("Failed to fetch default payment method for member {$member->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Set default payment method for customer
     */
    public function setDefaultPaymentMethod(Member $member, string $paymentMethodId): void
    {
        if (!$member->stripe_customer_id) {
            throw new \Exception('Member does not have a Stripe customer ID');
        }

        try {
            // Attach payment method to customer if not already attached
            $pm = $this->stripe->paymentMethods->retrieve($paymentMethodId);

            if ($pm->customer !== $member->stripe_customer_id) {
                $this->stripe->paymentMethods->attach($paymentMethodId, [
                    'customer' => $member->stripe_customer_id
                ]);
            }

            // Set as default
            $this->stripe->customers->update($member->stripe_customer_id, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId
                ]
            ]);
        } catch (\Exception $e) {
            throw new \Exception("Failed to set default payment method: " . $e->getMessage());
        }
    }

    /**
     * Detach payment method from customer
     */
    public function detachPaymentMethod(Member $member, string $paymentMethodId): bool
    {
        if (!$member->stripe_customer_id) {
            throw new \Exception('Member does not have a Stripe customer ID');
        }

        try {
            // Verify payment method belongs to this customer
            $pm = $this->stripe->paymentMethods->retrieve($paymentMethodId);

            if ($pm->customer !== $member->stripe_customer_id) {
                throw new \Exception('Payment method does not belong to this customer');
            }

            // Detach from customer
            $this->stripe->paymentMethods->detach($paymentMethodId);

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to detach payment method: " . $e->getMessage());
        }
    }

    /**
     * Create setup intent for saving a new card
     */
    public function createSetupIntent(Member $member): array
    {
        try {
            // Ensure customer exists
            if (!$member->stripe_customer_id) {
                $customer = $this->stripe->customers->create([
                    'email' => $member->email,
                    'name' => $member->first_name . ' ' . $member->last_name,
                    'metadata' => [
                        'member_id' => $member->id
                    ]
                ]);

                // Update member with customer ID
                $member->update(['stripe_customer_id' => $customer->id]);
            } else {
                $customer = (object)['id' => $member->stripe_customer_id];
            }

            // Create setup intent
            $setupIntent = $this->stripe->setupIntents->create([
                'customer' => $customer->id,
                'payment_method_types' => ['card'],
                'usage' => 'off_session'
            ]);

            return [
                'success' => true,
                'client_secret' => $setupIntent->client_secret,
                'setup_intent_id' => $setupIntent->id
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create setup intent: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify payment method belongs to member
     */
    public function verifyPaymentMethodOwnership(Member $member, string $paymentMethodId): bool
    {
        if (!$member->stripe_customer_id) {
            return false;
        }

        try {
            $pm = $this->stripe->paymentMethods->retrieve($paymentMethodId);
            return $pm->customer === $member->stripe_customer_id;
        } catch (\Exception $e) {
            return false;
        }
    }
}