<?php

declare(strict_types=1);

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\PayoutStatus;
use App\Framework\Support\Logger;
use App\Repositories\OpenCollab\ContributorPayoutAccountRepository;
use App\Repositories\OpenCollab\PayoutRepository;

class StripeConnectWebhookHandler
{
    public function __construct(
        private readonly ContributorPayoutAccountRepository $payoutAccountRepository,
        private readonly PayoutRepository                   $payoutRepository,
        private readonly Logger                             $logger,
    )
    {
    }

    public function handle(object $event, string $correlationId): void
    {
        match ($event->type) {
            'account.updated' => $this->handleAccountUpdated($event, $correlationId),
            'payout.paid' => $this->handlePayoutPaid($event, $correlationId),
            'payout.failed' => $this->handlePayoutFailed($event, $correlationId),
            'transfer.created' => $this->handleTransferCreated($event, $correlationId),
            default => null,
        };
    }

    private function handleAccountUpdated(object $event, string $correlationId): void
    {
        $account = $event->data->object ?? null;
        $stripeAccountId = $account?->id;
        if (!$stripeAccountId) {
            return;
        }

        $existing = $this->payoutAccountRepository->findByStripeAccountId((string)$stripeAccountId);
        if (!$existing) {
            return;
        }

        $requirements = (array)($account->requirements?->currently_due ?? []);
        $payoutsEnabled = (bool)($account->payouts_enabled ?? false);
        $detailsSubmitted = (bool)($account->details_submitted ?? false);

        $this->payoutAccountRepository->updateCapabilities((int)$existing->id, [
            'charges_enabled' => (bool)($account->charges_enabled ?? false),
            'payouts_enabled' => $payoutsEnabled,
            'details_submitted' => $detailsSubmitted,
            'requirements_due_json' => $requirements,
            'onboarding_completed_at' => ($payoutsEnabled && $detailsSubmitted && count($requirements) === 0)
                ? date('Y-m-d H:i:s')
                : null,
        ]);

        $this->logger->info('Stripe Connect account capability updated.', [
            'correlation_id' => $correlationId,
            'stripe_account_id' => $stripeAccountId,
            'user_id' => $existing->user_id,
            'payouts_enabled' => $payoutsEnabled,
        ]);
    }

    private function handlePayoutPaid(object $event, string $correlationId): void
    {
        $this->syncPayoutState($event, PayoutStatus::Paid, 'paid', $correlationId);
    }

    private function syncPayoutState(object $event, PayoutStatus $status, string $providerStatus, string $correlationId): void
    {
        $payload = $event->data->object ?? null;
        $transferId = (string)($payload?->source_transfer ?? $payload?->id ?? '');
        if ($transferId === '') {
            return;
        }

        $payout = $this->payoutRepository
            ->where('provider_transfer_id', '=', $transferId, applySiteFilter: false)
            ->first();

        if (!$payout) {
            return;
        }

        $this->payoutRepository->update((int)$payout->id, [
            'status' => $status->value,
            'provider_status' => $providerStatus,
            'processed_at' => date('Y-m-d H:i:s'),
            'provider_response_json' => [
                'event_id' => $event->id ?? null,
                'correlation_id' => $correlationId,
                'payout' => (array)$payload,
            ],
        ]);
    }

    private function handlePayoutFailed(object $event, string $correlationId): void
    {
        $this->syncPayoutState($event, PayoutStatus::Failed, 'failed', $correlationId);
    }

    private function handleTransferCreated(object $event, string $correlationId): void
    {
        $transfer = $event->data->object ?? null;
        $payoutId = (int)($transfer?->metadata?->payout_id ?? 0);
        if ($payoutId <= 0) {
            return;
        }

        $this->payoutRepository->update($payoutId, [
            'provider' => 'stripe_connect',
            'provider_transfer_id' => $transfer->id ?? null,
            'provider_status' => 'transfer_created',
            'provider_response_json' => [
                'event_id' => $event->id ?? null,
                'correlation_id' => $correlationId,
                'transfer' => (array)$transfer,
            ],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

