<?php

declare(strict_types=1);

namespace App\Jobs\OpenCollab;

use App\Enums\OpenCollab\PayoutStatus;
use App\Framework\Queue\ShouldBeUnique;
use App\Jobs\BaseJob;
use App\Models\Payout;
use App\Repositories\OpenCollab\ContributorPayoutAccountRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use Stripe\Exception\ApiErrorException;
use Stripe\Service\TransferService;
use Stripe\StripeClient;

class ProcessStripePayoutJob extends BaseJob implements ShouldBeUnique
{
    public int $tries = 5;
    public ?PayoutRepository $payoutRepository = null;

    // Dependencies (lazy for worker + testability)
    public ?ContributorPayoutAccountRepository $payoutAccountRepository = null;
    public ?StripeClient $stripe = null;

    public function __construct(
        public readonly int $payoutId,
    )
    {
    }

    public function uniqueId(): string
    {
        return 'stripe_payout:' . $this->payoutId;
    }

    public function uniqueFor(): int
    {
        // prevent duplicate execution for 30 minutes
        return 30 * 60;
    }

    public function handle(): void
    {
        $this->payoutRepository ??= new PayoutRepository();
        $this->payoutAccountRepository ??= new ContributorPayoutAccountRepository();
        $this->stripe ??= new StripeClient((string)($_ENV['STRIPE_SECRET_KEY'] ?? ''));

        /** @var Payout|null $payout */
        $payout = $this->payoutRepository->find($this->payoutId);

        if (!$payout) {
            return;
        }

        // Only process approved Stripe payouts
        if ($payout->status !== PayoutStatus::Approved->value) {
            return;
        }

        if ($payout->method !== 'stripe') {
            return;
        }

        // Idempotency: if we already created a transfer, do nothing
        if (!empty($payout->provider_transfer_id)) {
            return;
        }

        $account = $this->payoutAccountRepository->findByUserId((int)$payout->user_id, 'stripe');

        if (!$account || !$account->stripe_account_id || !$account->payouts_enabled) {
            $this->updatePayout($payout->id, [
                'status' => PayoutStatus::Failed->value,
                'provider' => 'stripe_connect',
                'provider_status' => 'account_not_payable',
                'processing_attempts' => ((int)($payout->processing_attempts ?? 0)) + 1,
                'processed_at' => date('Y-m-d H:i:s'),
                'provider_response_json' => [
                    'reason' => 'Connected account missing or payouts disabled.',
                ],
            ]);
            return;
        }

        try {
            /** @var TransferService $transfers */
            $transfers = $this->stripe->transfers;

            $transfer = $transfers->create([
                'amount' => (int)$payout->amount,
                'currency' => strtolower((string)$payout->currency),
                'destination' => (string)$account->stripe_account_id,
                'metadata' => [
                    'payout_id' => (string)$payout->id,
                    'user_id' => (string)$payout->user_id,
                    'site_id' => (string)$payout->site_id,
                ],
            ]);

            $attempts = !$payout->processing_attempts ? 1 : $payout->processing_attempts + 1;

            $this->updatePayout($payout->id, [
                'provider' => 'stripe_connect',
                'provider_transfer_id' => $transfer->id,
                'provider_status' => !$transfer->status ? 'created' : $transfer->status,
                'provider_response_json' => $transfer->toArray(),
                'processing_attempts' => $attempts,
                'processed_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (ApiErrorException $e) {
            echo $e->getMessage();
            die;
            $this->updatePayout($payout->id, [
                'status' => PayoutStatus::Failed->value,
                'provider' => 'stripe_connect',
                'provider_status' => 'transfer_failed',
                'processing_attempts' => ((int)($payout->processing_attempts ?? 0)) + 1,
                'processed_at' => date('Y-m-d H:i:s'),
                'provider_response_json' => [
                    'error' => $e->getMessage(),
                ],
            ]);
        }
    }

    private function updatePayout(int $payoutId, array $data): void
    {
        // Store the Stripe payload for audit/debugging.
        if ($data['provider_response_json'] && is_array($data['provider_response_json'])) {
            $data['provider_response_json'] = json_encode($data['provider_response_json']);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        $this->payoutRepository->update($payoutId, $data);
    }
}

