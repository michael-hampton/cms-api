<?php

declare(strict_types=1);

namespace App\Jobs\OpenCollab;

use App\Enums\OpenCollab\PayoutStatus;
use App\Framework\Queue\ShouldBeUnique;
use App\Jobs\BaseJob;
use App\Models\Payout;
use App\Repositories\OpenCollab\ContributorPayoutAccountRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Services\OpenCollab\PayoutLedgerService;
use Exception;
use Stripe\Exception\ApiErrorException;
use Stripe\Service\TransferService;
use Stripe\StripeClient;

class ProcessStripePayoutJob extends BaseJob implements ShouldBeUnique
{
    public int $tries = 5;

    // Dependencies are public nullable so tests can inject mocks directly.
    public ?PayoutRepository $payoutRepository = null;
    public ?ContributorPayoutAccountRepository $payoutAccountRepository = null;
    public ?PayoutLedgerService $payoutLedgerService = null;
    public ?StripeClient $stripe = null;

    public function __construct(
        public readonly int $payoutId,
    ) {
    }

    public function uniqueId(): string
    {
        return 'stripe_payout:' . $this->payoutId;
    }

    public function uniqueFor(): int
    {
        // Prevent duplicate execution for 30 minutes.
        return 30 * 60;
    }

    public function handle(): void
    {
        $this->payoutRepository ??= new PayoutRepository();
        $this->payoutAccountRepository ??= new ContributorPayoutAccountRepository();
        $this->stripe ??= new StripeClient((string)($_ENV['STRIPE_SECRET_KEY'] ?? ''));
        $this->payoutLedgerService ??= app(PayoutLedgerService::class);

        /** @var Payout|null $payout */
        $payout = $this->payoutRepository->find($this->payoutId);

        if (!$payout) {
            return;
        }

        // Only process approved payouts.
        if ($payout->status !== PayoutStatus::Approved->value) {
            return;
        }

        // In this app, bank_account is still Stripe Express-backed.
        if (!$this->isStripeBackedMethod((string) $payout->method)) {
            return;
        }

        // Idempotency guard: if a Stripe transfer already exists, do not create another.
        if (!empty($payout->provider_transfer_id)) {
            return;
        }

        $attempt = ((int) ($payout->processing_attempts ?? 0)) + 1;

        $account = $this->payoutAccountRepository->findByUserId((int) $payout->user_id, 'stripe');

        if (!$account || !$account->stripe_account_id || !$account->payouts_enabled) {
            $this->updatePayout($this->payoutRepository, (int) $payout->id, [
                'status' => PayoutStatus::Failed->value,
                'provider' => 'stripe_connect',
                'provider_status' => 'account_not_payable',
                'processing_attempts' => $attempt,
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
                'amount' => (int) $payout->amount,
                'currency' => strtolower((string) $payout->currency),
                'destination' => (string) $account->stripe_account_id,
                'metadata' => [
                    'payout_id' => (string) $payout->id,
                    'user_id' => (string) $payout->user_id,
                    'site_id' => (string) $payout->site_id,
                ],
            ], [
                'idempotency_key' => $this->stripeTransferIdempotencyKey($payout, $attempt),
            ]);

            $this->updatePayout($this->payoutRepository, (int) $payout->id, [
                'status' => PayoutStatus::Paid->value,
                'provider' => 'stripe_connect',
                'provider_transfer_id' => $transfer->id,
                'provider_status' => !$transfer->status ? 'created' : $transfer->status,
                'provider_response_json' => $transfer->toArray(),
                'processing_attempts' => $attempt,
                'processed_at' => date('Y-m-d H:i:s'),
            ]);

            // This is the important missing step.
            // Once Stripe has accepted the transfer, the attached settled earnings
            // should no longer remain withdrawable.
            $this->payoutLedgerService->markPayoutLedgerEntriesWithdrawn((int) $payout->id);
        } catch (ApiErrorException $e) {
            $this->markPayoutFailed((int) $payout->id, $attempt, 'transfer_failed', $e);
        } catch (\Throwable $e) {
            $this->markPayoutFailed((int) $payout->id, $attempt, 'processing_failed', $e);
        }
    }

    public function failed(Exception $exception): void
    {
        parent::failed($exception);

        $this->payoutRepository ??= new PayoutRepository();

        $payout = $this->payoutRepository->find($this->payoutId);

        if (!$payout || $payout->status !== PayoutStatus::Approved->value) {
            return;
        }

        $this->markPayoutFailed(
            (int) $payout->id,
            (int) ($payout->processing_attempts ?? 0),
            'job_failed',
            $exception,
        );
    }

    private function isStripeBackedMethod(string $method): bool
    {
        return $method === 'stripe';
    }

    private function stripeTransferIdempotencyKey(Payout $payout, int $attempt): string
    {
        return sprintf(
            'stripe-transfer:payout:%d:attempt:%d',
            (int) $payout->id,
            $attempt
        );
    }

    private function updatePayout(
        PayoutRepository $payoutRepository,
        int $payoutId,
        array $data,
    ): void {
        // Store the Stripe payload for audit/debugging.
        if (isset($data['provider_response_json']) && is_array($data['provider_response_json'])) {
            $data['provider_response_json'] = json_encode($data['provider_response_json']);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        $payoutRepository->update($payoutId, $data);
    }

    private function markPayoutFailed(
        int $payoutId,
        int $attempt,
        string $providerStatus,
        \Throwable $exception,
    ): void {
        $this->updatePayout($this->payoutRepository, $payoutId, [
            'status' => PayoutStatus::Failed->value,
            'provider' => 'stripe_connect',
            'provider_status' => $providerStatus,
            'processing_attempts' => $attempt,
            'processed_at' => date('Y-m-d H:i:s'),
            'provider_response_json' => [
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ],
        ]);
    }
}
