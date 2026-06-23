<?php

namespace App\Services\Billing\Stripe;

use App\DTO\Billing\PaymentMethodDto;
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
            $defaultPaymentMethodId = (string) ($customer->invoice_settings->default_payment_method ?? '');
            $canRemove = count($methods->data) > 1 || !$this->hasRecurringBilling($member);

            return [
                'success' => true,
                'payment_methods' => array_map(
                    fn ($method) => PaymentMethodDto::fromStripe($method, $defaultPaymentMethodId, $canRemove)->toArray(),
                    $methods->data
                ),
                'default_payment_method_id' => $defaultPaymentMethodId !== '' ? $defaultPaymentMethodId : null,
            ];
        } catch (Exception) {
            return [
                'success' => false,
                'payment_methods' => [],
                'default_payment_method_id' => null,
                'message' => 'Failed to fetch payment methods',
            ];
        }
    }

    public function getMemberPaymentMethods(Member $member): array
    {
        if (!$member->stripe_customer_id) {
            return [];
        }

        try {
            $methods = $this->stripe->paymentMethods->all([
                'customer' => $member->stripe_customer_id,
                'type' => 'card',
            ]);

            return array_map(
                fn ($method) => PaymentMethodDto::fromStripe($method)->toSavedCardArray(),
                $methods->data
            );
        } catch (Exception $e) {
            error_log("Failed to fetch payment methods for member {$member->id}: " . $e->getMessage());
            return [];
        }
    }

    public function getDefaultPaymentMethod(Member $member): ?array
    {
        if (!$member->stripe_customer_id) {
            return null;
        }

        try {
            $customer = $this->stripe->customers->retrieve($member->stripe_customer_id);
            $defaultPaymentMethodId = (string) ($customer->invoice_settings->default_payment_method ?? '');

            if ($defaultPaymentMethodId === '') {
                return null;
            }

            $method = $this->stripe->paymentMethods->retrieve($defaultPaymentMethodId);

            return PaymentMethodDto::fromStripe($method, $defaultPaymentMethodId)->toSavedCardArray();
        } catch (Exception $e) {
            error_log("Failed to fetch default payment method for member {$member->id}: " . $e->getMessage());
            return null;
        }
    }

    public function addPaymentMethod(Member $member, string $paymentMethodId, bool $setDefault = false): array
    {
        try {
            $customerId = $this->ensureCustomer($member);

            $paymentMethod = $this->stripe->paymentMethods->retrieve($paymentMethodId);
            if (($paymentMethod->customer ?? null) !== $customerId) {
                $this->stripe->paymentMethods->attach($paymentMethodId, [
                    'customer' => $customerId,
                ]);
            }

            if ($setDefault) {
                $this->stripe->customers->update($customerId, [
                    'invoice_settings' => [
                        'default_payment_method' => $paymentMethodId,
                    ],
                ]);
            }

            return ['success' => true];
        } catch (Exception) {
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
                'metadata' => ['member_id' => (string) $member->id],
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

            return [
                'success' => true,
                'payment_method' => PaymentMethodDto::fromStripe(
                    $paymentMethod,
                    $setDefault ? (string) $paymentMethod->id : ''
                )->toArray(),
            ];
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
        } catch (Exception) {
            return [
                'success' => false,
                'message' => 'Failed to update default payment method',
            ];
        }
    }

    public function setDefaultPaymentMethodForMember(Member $member, string $paymentMethodId): array
    {
        if (!$member->stripe_customer_id) {
            return [
                'success' => false,
                'message' => 'Member does not have a Stripe customer ID',
                'error_code' => 'missing_customer',
            ];
        }

        try {
            $paymentMethod = $this->stripe->paymentMethods->retrieve($paymentMethodId);

            if (($paymentMethod->customer ?? null) !== $member->stripe_customer_id) {
                $this->stripe->paymentMethods->attach($paymentMethodId, [
                    'customer' => $member->stripe_customer_id,
                ]);
            }

            return $this->setDefaultPaymentMethod((string) $member->stripe_customer_id, $paymentMethodId);
        } catch (Exception) {
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
        } catch (Exception) {
            return [
                'success' => false,
                'message' => 'Failed to remove payment method',
            ];
        }
    }

    public function detachPaymentMethod(Member $member, string $paymentMethodId): bool
    {
        $result = $this->removePaymentMethod($member, $paymentMethodId);

        if (!($result['success'] ?? false)) {
            throw new Exception($result['message'] ?? 'Failed to detach payment method');
        }

        return true;
    }

    public function verifyPaymentMethodOwnership(Member $member, string $paymentMethodId): bool
    {
        if (!$member->stripe_customer_id) {
            return false;
        }

        try {
            $method = $this->stripe->paymentMethods->retrieve($paymentMethodId);

            return ($method->customer ?? null) === $member->stripe_customer_id;
        } catch (Exception) {
            return false;
        }
    }

    private function ensureCustomer(Member $member): string
    {
        if ($member->stripe_customer_id) {
            return (string) $member->stripe_customer_id;
        }

        $customer = $this->stripe->customers->create([
            'email' => $member->email,
            'name' => $this->memberName($member),
            'metadata' => [
                'member_id' => (string) $member->id,
                'site_id' => (string) ($member->site_id ?? ''),
            ],
        ]);
        $member->update(['stripe_customer_id' => $customer->id]);

        return (string) $customer->id;
    }

    private function memberName(Member $member): string
    {
        $name = $member->full_name
            ?? trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''))
            ?: ($member->display_name ?? $member->email);

        return (string) $name;
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
