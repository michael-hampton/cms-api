<?php

namespace App\Services\OpenCollab;

use App\Framework\Support\Logger;
use App\Models\ContributorProfile;
use App\Models\User;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use RuntimeException;
use Stripe\StripeClient;
use Throwable;

class ContributorPaymentMethodService
{
    private ?StripeClient $stripe;

    public function __construct(
        private readonly ContributorProfileRepository $profileRepository,
        private readonly Logger $logger,
        ?StripeClient $stripeClient = null,
    ) {
        $this->stripe = $stripeClient;
    }

    public function listForUser(User $user): array
    {
        $profile = $this->profileRepository->findByUserId((int)$user->id);

        if (!$profile || empty($profile->stripe_customer_id)) {
            return [
                'success' => true,
                'payment_methods' => [],
                'default_payment_method_id' => null,
            ];
        }

        try {
            $stripe = $this->stripe();
            $customer = $stripe->customers->retrieve($profile->stripe_customer_id);
            $methods = $stripe->paymentMethods->all([
                'customer' => $profile->stripe_customer_id,
                'type' => 'card',
            ]);

            $defaultId = $customer->invoice_settings->default_payment_method ?? null;

            return [
                'success' => true,
                'payment_methods' => array_map(
                    fn($method): array => $this->formatPaymentMethod($method, (string)$defaultId),
                    $methods->data ?? []
                ),
                'default_payment_method_id' => $defaultId,
            ];
        } catch (Throwable $e) {
            Logger::error('Failed to load Stripe payment methods for contributor.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'payment_methods' => [],
                'default_payment_method_id' => null,
                'message' => 'Failed to load payment methods.',
            ];
        }
    }

    public function addForUser(
        User $user,
        string $paymentMethodId,
        ?string $taxCountry = null,
        bool $setDefault = true,
    ): array {
        try {
            $profile = $this->profileRepository->findOrCreateForUser((int)$user->id);
            $customerId = $this->resolveCustomerId($user, $profile);
            $stripe = $this->stripe();
            $paymentMethod = $stripe->paymentMethods->retrieve($paymentMethodId);
            $attachedCustomerId = $this->stripeCustomerId($paymentMethod->customer ?? null);

            if ($attachedCustomerId !== '' && $attachedCustomerId !== $customerId) {
                return [
                    'success' => false,
                    'message' => 'This payment method belongs to another Stripe customer.',
                    'error_code' => 'unauthorized',
                ];
            }

            if ($attachedCustomerId === '') {
                $stripe->paymentMethods->attach($paymentMethodId, [
                    'customer' => $customerId,
                ]);
            }

            if ($setDefault) {
                $this->setStripeDefault($customerId, $paymentMethodId);
            }

            $this->profileRepository->update((int)$profile->id, array_filter([
                'payment_method_type' => 'stripe',
                'payment_details' => $paymentMethodId,
                'stripe_customer_id' => $customerId,
                'tax_country' => $taxCountry,
            ], static fn($value) => $value !== null));

            return $this->listForUser($user);
        } catch (Throwable $e) {
            Logger::error('Failed to save Stripe payment method for contributor.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to save payment method.',
            ];
        }
    }

    public function setDefaultForUser(User $user, string $paymentMethodId): array
    {
        $profile = $this->profileRepository->findByUserId((int)$user->id);

        if (!$profile || empty($profile->stripe_customer_id)) {
            return ['success' => false, 'message' => 'No Stripe customer found.'];
        }

        try {
            if (!$this->paymentMethodBelongsToCustomer($paymentMethodId, $profile->stripe_customer_id)) {
                return ['success' => false, 'message' => 'Unauthorized.', 'error_code' => 'unauthorized'];
            }

            $this->setStripeDefault($profile->stripe_customer_id, $paymentMethodId);
            $this->profileRepository->update((int)$profile->id, [
                'payment_method_type' => 'stripe',
                'payment_details' => $paymentMethodId,
            ]);

            return $this->listForUser($user);
        } catch (Throwable $e) {
            Logger::error('Failed to update default Stripe payment method for contributor.', [
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Failed to update default payment method.'];
        }
    }

    public function removeForUser(User $user, string $paymentMethodId): array
    {
        $profile = $this->profileRepository->findByUserId((int)$user->id);

        if (!$profile || empty($profile->stripe_customer_id)) {
            return ['success' => false, 'message' => 'No Stripe customer found.'];
        }

        try {
            if (!$this->paymentMethodBelongsToCustomer($paymentMethodId, $profile->stripe_customer_id)) {
                return ['success' => false, 'message' => 'Unauthorized.', 'error_code' => 'unauthorized'];
            }

            $stripe = $this->stripe();
            $customer = $stripe->customers->retrieve($profile->stripe_customer_id);
            $wasDefault = (string)($customer->invoice_settings->default_payment_method ?? '') === $paymentMethodId;

            $stripe->paymentMethods->detach($paymentMethodId);

            if ($wasDefault) {
                $remaining = $stripe->paymentMethods->all([
                    'customer' => $profile->stripe_customer_id,
                    'type' => 'card',
                    'limit' => 1,
                ]);
                $nextDefaultId = $remaining->data[0]->id ?? null;
                $this->setStripeDefault($profile->stripe_customer_id, $nextDefaultId);
                $this->profileRepository->update((int)$profile->id, [
                    'payment_details' => $nextDefaultId,
                ]);
            }

            return $this->listForUser($user);
        } catch (Throwable $e) {
            Logger::error('Failed to remove Stripe payment method for contributor.', [
                'user_id' => $user->id,
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Failed to remove payment method.'];
        }
    }

    private function resolveCustomerId(User $user, ContributorProfile $profile): string
    {
        if (!empty($profile->stripe_customer_id)) {
            return (string)$profile->stripe_customer_id;
        }

        $customer = $this->stripe()->customers->create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id,
                'site_id' => $user->site_id ?? null,
                'context' => 'open_collab_contributor',
            ],
        ]);

        try {
            $this->profileRepository->update((int)$profile->id, [
                'stripe_customer_id' => $customer->id,
            ]);
        } catch (Throwable $e) {
            // The Stripe customer now exists live but we failed to record it.
            // A DB transaction can't roll back the Stripe side effect, so make
            // sure the orphaned customer id is discoverable for reconciliation
            // instead of silently disappearing into the caller's generic catch.
            $this->logger->error('Stripe customer created but failed to persist stripe_customer_id.', [
                'user_id' => $user->id,
                'contributor_profile_id' => $profile->id,
                'stripe_customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return (string)$customer->id;
    }

    private function paymentMethodBelongsToCustomer(string $paymentMethodId, string $customerId): bool
    {
        $paymentMethod = $this->stripe()->paymentMethods->retrieve($paymentMethodId);

        return $this->stripeCustomerId($paymentMethod->customer ?? null) === $customerId;
    }

    private function setStripeDefault(string $customerId, ?string $paymentMethodId): void
    {
        $this->stripe()->customers->update($customerId, [
            'invoice_settings' => [
                'default_payment_method' => $paymentMethodId,
            ],
        ]);
    }

    private function formatPaymentMethod($method, ?string $defaultId): array
    {
        return [
            'id' => $method->id,
            'brand' => $method->card->brand ?? 'card',
            'last4' => $method->card->last4 ?? '',
            'exp_month' => $method->card->exp_month ?? null,
            'exp_year' => $method->card->exp_year ?? null,
            'is_default' => $method->id === $defaultId,
        ];
    }

    private function stripe(): StripeClient
    {
        if ($this->stripe) {
            return $this->stripe;
        }

        $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? config('payment.stripe.secret_key') ?? null;

        if (!$secretKey) {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        $this->stripe = new StripeClient((string)$secretKey);

        return $this->stripe;
    }

    private function stripeCustomerId(mixed $customer): string
    {
        if (is_string($customer)) {
            return $customer;
        }

        if (is_object($customer) && isset($customer->id)) {
            return (string)$customer->id;
        }

        return '';
    }
}
