<?php

namespace App\Services\Billing\Stripe;

use App\Models\Member;
use App\Models\Subscription;
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
            $canRemove = count($methods->data) > 1 || !$this->hasRecurringBilling($member);

            return [
                'success' => true,
                'payment_methods' => array_map(
                    fn($method) => $this->mapPaymentMethod(
                        $method,
                        (string)($customer->invoice_settings->default_payment_method ?? ''),
                        $canRemove,
                    ),
                    $methods->data
                ),
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
                'message' => 'Failed to add payment method.',
            ];
        }
    }

    public function createSetupIntent(Member $member): array
    {
        try {
            $customerId = $this->ensureCustomer($member);
            $intent = $this->stripe->setupIntents->create([
                'customer' => $customerId,
                'usage' => 'off_session',
                'payment_method_types' => ['card'],
                'metadata' => ['member_id' => (string)$member->id],
            ]);

            return [
                'success' => true,
                'setup_intent_id' => $intent->id,
                'client_secret' => $intent->client_secret,
            ];
        } catch (\Throwable) {
            return ['success' => false, 'message' => 'Unable to initialise card setup.'];
        }
    }

    public function finaliseSetupIntent(Member $member, string $setupIntentId, bool $setDefault): array
    {
        try {
            $customerId = $this->ensureCustomer($member);
            $intent = $this->stripe->setupIntents->retrieve($setupIntentId);

            if (($intent->customer ?? null) !== $customerId
                || ($intent->status ?? null) !== 'succeeded'
                || empty($intent->payment_method)) {
                return ['success' => false, 'message' => 'Card setup could not be verified.'];
            }

            $paymentMethod = $this->stripe->paymentMethods->retrieve($intent->payment_method);
            if (($paymentMethod->customer ?? null) !== $customerId) {
                return ['success' => false, 'message' => 'Card setup could not be verified.'];
            }

            if ($setDefault) {
                $this->stripe->customers->update($customerId, [
                    'invoice_settings' => ['default_payment_method' => $paymentMethod->id],
                ]);
            }

            return ['success' => true, 'payment_method' => $this->mapPaymentMethod($paymentMethod, $setDefault ? $paymentMethod->id : '')];
        } catch (\Throwable) {
            return ['success' => false, 'message' => 'Card setup could not be verified.'];
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

            $methods = $this->stripe->paymentMethods->all([
                'customer' => $member->stripe_customer_id,
                'type' => 'card',
            ]);
            $hasRecurringBilling = $this->hasRecurringBilling($member);

            if ($hasRecurringBilling && count($methods->data) <= 1) {
                return [
                    'success' => false,
                    'message' => 'Add another card before removing the only payment method used for recurring billing.',
                    'error_code' => 'last_required_method',
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

    private function ensureCustomer(Member $member): string
    {
        if ($member->stripe_customer_id) {
            return (string)$member->stripe_customer_id;
        }

        $customer = $this->stripe->customers->create([
            'email' => $member->email,
            'name' => $member->full_name ?? $member->display_name ?? $member->email,
            'metadata' => [
                'member_id' => (string)$member->id,
                'site_id' => (string)$member->site_id,
            ],
        ]);
        $member->update(['stripe_customer_id' => $customer->id]);

        return (string)$customer->id;
    }

    private function mapPaymentMethod(object $method, string $defaultId, bool $canRemove = true): array
    {
        return [
            'id' => (string)$method->id,
            'brand' => (string)($method->card->brand ?? 'card'),
            'last4' => (string)($method->card->last4 ?? ''),
            'exp_month' => (int)($method->card->exp_month ?? 0),
            'exp_year' => (int)($method->card->exp_year ?? 0),
            'is_default' => (string)$method->id === $defaultId,
            'can_remove' => $canRemove,
        ];
    }

    private function hasRecurringBilling(Member $member): bool
    {
        if (!$member->id || !$member->site_id) {
            return false;
        }

        return Subscription::where('member_id', $member->id)
            ->where('site_id', $member->site_id)
            ->whereIn('status', ['active', 'trialing', 'past_due', 'unpaid', 'retrying'])
            ->where('auto_renew', true)
            ->exists();
    }
}
