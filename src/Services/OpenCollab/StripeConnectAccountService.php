<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\StripeConnectAccountStatus;
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
                    'type'    => 'express',
                    'country' => 'GB',
                    'email'   => $user?->email,
                    'metadata' => [
                        'user_id'  => (string)$userId,
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
                        'user_id'              => $userId,
                        'provider'             => 'stripe',
                        'stripe_account_id'    => $stripeAccountId,
                        'charges_enabled'      => (bool)$account->charges_enabled,
                        'payouts_enabled'      => (bool)$account->payouts_enabled,
                        'details_submitted'    => (bool)$account->details_submitted,
                        'requirements_due_json' => (array)($account->requirements?->currently_due ?? []),
                    ]);
                }
            }

            $link = $this->stripe->accountLinks->create([
                'account'     => $stripeAccountId,
                'type'        => 'account_onboarding',
                'return_url'  => $returnUrl,
                'refresh_url' => $refreshUrl,
            ]);

            return [
                'success'           => true,
                'onboarding_url'    => $link->url,
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

    /**
     * Returns the current onboarding status for a contributor's Stripe Connect account.
     *
     * The `status` field is now driven by StripeConnectAccountStatus, keeping all
     * status derivation logic in one place and eliminating magic strings.
     *
     * @return array{
     *   connected: bool,
     *   status: string,
     *   stripe_account_id?: string,
     *   payouts_enabled: bool,
     *   verification_required: array,
     * }
     */
    public function getOnboardingStatus(int $userId): array
    {
        $account = $this->payoutAccountRepository->findByUserId($userId, 'stripe');

        if (!$account || empty($account->stripe_account_id)) {
            return [
                'connected'            => false,
                'status'               => StripeConnectAccountStatus::Disconnected->value,
                'payouts_enabled'      => false,
                'verification_required' => [],
            ];
        }

        $requirements    = (array)($account->requirements_due_json ?? []);
        $payoutsEnabled  = (bool)$account->payouts_enabled;
        $detailsSubmitted = (bool)$account->details_submitted;

        $status = StripeConnectAccountStatus::fromAccountFields(
            connected:        true,
            detailsSubmitted: $detailsSubmitted,
            payoutsEnabled:   $payoutsEnabled,
            requirementsDue:  $requirements,
        );

        return [
            'connected'            => true,
            'status'               => $status->value,
            'stripe_account_id'    => $account->stripe_account_id,
            'payouts_enabled'      => $payoutsEnabled,
            'verification_required' => $requirements,
        ];
    }

    /**
     * Resolve the typed status enum for a user's Stripe Connect account.
     * Useful internally and for other services that need to branch on status.
     */
    public function getAccountStatus(int $userId): StripeConnectAccountStatus
    {
        $raw = $this->getOnboardingStatus($userId);

        return StripeConnectAccountStatus::from($raw['status']);
    }
}