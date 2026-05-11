<?php

namespace App\Services\OpenCollab;

use App\Models\User;
use App\Repositories\OpenCollab\ContributorPayoutAccountRepository;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeConnectAccountService
{
    private StripeClient $stripe;

    public function __construct(
        private readonly ContributorPayoutAccountRepository $payoutAccountRepository,
        ?StripeClient                                       $stripe = null,
    )
    {
        $secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;
        $this->stripe = $stripe ?: new StripeClient((string)$secretKey);
    }

    /**
     * Create (or reuse) a Stripe Express account and return an onboarding URL.
     *
     * @return array{success: bool, onboarding_url?: string, stripe_account_id?: string, message?: string}
     */
    public function createOrRefreshOnboarding(int $userId, string $returnUrl, string $refreshUrl): array
    {
        $existing = $this->payoutAccountRepository->findByUserId($userId, 'stripe');

        try {
            $stripeAccountId = $existing?->stripe_account_id;

            if (!$stripeAccountId) {
                $user = User::find($userId);

                $account = $this->stripe->accounts->create([
                    'type' => 'express',
                    // country can be updated by Stripe during onboarding; default to GB for now
                    'country' => 'GB',
                    'email' => $user?->email,
                    'metadata' => [
                        'user_id' => (string)$userId,
                        'provider' => 'stripe_connect',
                    ],
                ]);

                $stripeAccountId = $account->id;

                if ($existing) {
                    $this->payoutAccountRepository->update($existing->id, [
                        'stripe_account_id' => $stripeAccountId,
                    ]);
                    $existing = $this->payoutAccountRepository->find($existing->id);
                } else {
                    $existing = $this->payoutAccountRepository->create([
                        'user_id' => $userId,
                        'provider' => 'stripe',
                        'stripe_account_id' => $stripeAccountId,
                        'charges_enabled' => (bool)$account->charges_enabled,
                        'payouts_enabled' => (bool)$account->payouts_enabled,
                        'details_submitted' => (bool)$account->details_submitted,
                        'requirements_due_json' => (array)($account->requirements?->currently_due ?? []),
                    ]);
                }
            }

            $link = $this->stripe->accountLinks->create([
                'account' => $stripeAccountId,
                'type' => 'account_onboarding',
                'return_url' => $returnUrl,
                'refresh_url' => $refreshUrl,
            ]);

            return [
                'success' => true,
                'onboarding_url' => $link->url,
                'stripe_account_id' => $stripeAccountId,
            ];

        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Unable to start Stripe onboarding right now.',
            ];
        }
    }

    public function getOnboardingStatus(int $userId): array
    {
        $account = $this->payoutAccountRepository->findByUserId($userId, 'stripe');

        if (!$account || empty($account->stripe_account_id)) {
            return [
                'connected' => false,
                'status' => 'disconnected',
                'payouts_enabled' => false,
                'verification_required' => [],
            ];
        }

        $requirements = (array)($account->requirements_due_json ?? []);
        $payoutsEnabled = (bool)$account->payouts_enabled;
        $detailsSubmitted = (bool)$account->details_submitted;

        $status = 'verification_pending';
        if ($payoutsEnabled && $detailsSubmitted && count($requirements) === 0) {
            $status = 'enabled';
        } elseif (!$detailsSubmitted) {
            $status = 'incomplete';
        } elseif (!$payoutsEnabled) {
            $status = 'restricted';
        }

        return [
            'connected' => true,
            'status' => $status,
            'stripe_account_id' => $account->stripe_account_id,
            'payouts_enabled' => $payoutsEnabled,
            'verification_required' => $requirements,
        ];
    }
}

