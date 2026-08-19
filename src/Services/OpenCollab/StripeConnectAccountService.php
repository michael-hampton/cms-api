<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\StripeConnectAccountStatus;
use App\Framework\Support\Logger;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\ContributorPayoutAccountRepository;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;

class StripeConnectAccountService
{
    private StripeClient $stripe;

    public function __construct(
        private readonly ContributorPayoutAccountRepository $payoutAccountRepository,
        private readonly UserRepositoryInterface             $userRepository,
        private readonly Logger                             $logger,
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
                $user = $this->userRepository->find($userId);

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

                try {
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
                } catch (Throwable $e) {
                    // The Stripe Express account now exists live but we failed to
                    // record it locally. Log the live account id so it's
                    // discoverable/reconcilable instead of just disappearing.
                    $this->logger->error('Stripe Connect account created but failed to persist locally.', [
                        'user_id' => $userId,
                        'stripe_account_id' => $stripeAccountId,
                        'error' => $e->getMessage(),
                    ]);

                    throw $e;
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
            $this->logger->warning('Stripe API error while starting Connect onboarding.', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error while starting Stripe Connect onboarding.', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

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

        try {
            $stripeAccount = $this->stripe->accounts->retrieve($account->stripe_account_id);

            $requirements = (array)($stripeAccount->requirements?->currently_due ?? []);

            $this->payoutAccountRepository->update($account->id, [
                'charges_enabled'       => (bool) $stripeAccount->charges_enabled,
                'payouts_enabled'       => (bool) $stripeAccount->payouts_enabled,
                'details_submitted'     => (bool) $stripeAccount->details_submitted,
                'requirements_due_json' => $requirements,
            ]);

            $account = $this->payoutAccountRepository->find($account->id);
        } catch (ApiErrorException $e) {
            // Keep local values if Stripe is temporarily unavailable.
            $this->logger->error('Failed to refresh Stripe Connect account status from Stripe.', [
                'user_id' => $userId,
                'stripe_account_id' => $account->stripe_account_id,
                'error' => $e->getMessage(),
            ]);
        }

        $requirements     = (array)($account->requirements_due_json ?? []);
        $payoutsEnabled   = (bool)$account->payouts_enabled;
        $detailsSubmitted = (bool)$account->details_submitted;

        $status = StripeConnectAccountStatus::fromAccountFields(
            connected:        true,
            detailsSubmitted: $detailsSubmitted,
            payoutsEnabled:   $payoutsEnabled,
            requirementsDue:  $requirements,
        );

        return [
            'connected'             => true,
            'status'                => $status->value,
            'stripe_account_id'     => $account->stripe_account_id,
            'payouts_enabled'       => $payoutsEnabled,
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