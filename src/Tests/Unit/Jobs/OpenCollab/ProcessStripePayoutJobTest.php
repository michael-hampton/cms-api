<?php

declare(strict_types=1);

namespace App\Tests\Unit\Jobs\OpenCollab;

use App\Enums\OpenCollab\PayoutStatus;
use App\Jobs\OpenCollab\ProcessStripePayoutJob;
use App\Models\ContributorPayoutAccount;
use App\Models\Payout;
use App\Repositories\OpenCollab\ContributorPayoutAccountRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery as m;
use Stripe\Service\TransferService;
use Stripe\StripeClient;

class ProcessStripePayoutJobTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_job_skips_non_approved_payouts(): void
    {
        $user = $this->createUser();

        $payout = Payout::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'amount' => 5000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Pending->value,
            'method' => 'stripe',
        ]);

        $job = new ProcessStripePayoutJob($payout->id);
        $job->stripe = m::mock(StripeClient::class);
        $job->handle();

        $fresh = Payout::find($payout->id);
        $this->assertEquals(PayoutStatus::Pending->value, $fresh->status);
    }

    public function test_job_prevents_duplicate_processing_when_transfer_id_exists(): void
    {
        $user = $this->createUser();

        $payout = Payout::create([
            'user_id' => $user->id,
            'site_id' => $this->siteId,
            'amount' => 5000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Approved->value,
            'method' => 'stripe',
            'provider_transfer_id' => 'tr_existing',
        ]);

        $stripe = m::mock(StripeClient::class);
        $stripe->transfers = m::mock(TransferService::class);
        $stripe->transfers->shouldReceive('create')->never();

        $job = new ProcessStripePayoutJob($payout->id);
        $job->stripe = $stripe;
        $job->handle();

        $fresh = Payout::find($payout->id);
        $this->assertEquals('tr_existing', $fresh->provider_transfer_id);
    }

    public function test_job_creates_transfer_and_persists_provider_ids(): void
    {
        $user = $this->createUser();
        $userId = $user->id;

        ContributorPayoutAccount::create([
            'user_id' => $userId,
            'provider' => 'stripe',
            'stripe_account_id' => 'acct_dest_' . uniqid(),
            'payouts_enabled' => true,
        ]);

        $payout = Payout::create([
            'user_id' => $userId,
            'site_id' => $this->siteId,
            'amount' => 7777,
            'currency' => 'GBP',
            'status' => PayoutStatus::Approved->value,
            'method' => 'stripe',
            'processing_attempts' => 0,
        ]);

        $transfer = new \stdClass();
        $transfer->id = 'tr_new';
        $transfer->status = 'paid';
        $transfer->toArray = fn() => ['id' => 'tr_new', 'status' => 'paid'];

        // Stripe objects in stripe-php have toArray() method; simulate it
        $transferObj = new class {
            public string $id = 'tr_new';
            public string $status = 'paid';

            public function toArray(): array
            {
                return ['id' => 'tr_new', 'status' => 'paid'];
            }
        };

        $stripe = m::mock(StripeClient::class);
        $stripe->transfers = m::mock(TransferService::class);
        $stripe->transfers->shouldReceive('create')
            ->once()
            ->with(
                m::on(function (array $payload) use ($payout): bool {
                    return $payload['amount'] === (int) $payout->amount
                        && $payload['currency'] === 'gbp'
                        && str_starts_with($payload['destination'], 'acct_dest_')
                        && $payload['metadata']['payout_id'] === (string) $payout->id
                        && $payload['metadata']['user_id'] === (string) $payout->user_id
                        && $payload['metadata']['site_id'] === (string) $payout->site_id;
                }),
                m::on(function (array $options) use ($payout): bool {
                    return $options['idempotency_key'] === 'payout:' . $payout->id;
                }),
            )
            ->andReturn($transferObj);

        $job = new ProcessStripePayoutJob($payout->id);
        $job->stripe = $stripe;
        $job->payoutRepository = new PayoutRepository();
        $job->payoutAccountRepository = new ContributorPayoutAccountRepository();
        $job->handle();

        $this->assertDatabaseHas('oc_payouts', [
            'id' => $payout->id,
            'provider_transfer_id' => 'tr_new',
        ]);

        $fresh = Payout::find($payout->id);
        $this->assertEquals('stripe_connect', $fresh->provider);
        $this->assertEquals('tr_new', $fresh->provider_transfer_id);
        $this->assertEquals(1, $fresh->processing_attempts);
        $this->assertIsArray($fresh->provider_response_json);
    }

    public function test_job_uses_existing_payout_idempotency_key_when_present(): void
    {
        $user = $this->createUser();
        $userId = $user->id;

        ContributorPayoutAccount::create([
            'user_id' => $userId,
            'provider' => 'stripe',
            'stripe_account_id' => 'acct_dest_' . uniqid(),
            'payouts_enabled' => true,
        ]);

        $payout = Payout::create([
            'user_id' => $userId,
            'site_id' => $this->siteId,
            'amount' => 7777,
            'currency' => 'GBP',
            'status' => PayoutStatus::Approved->value,
            'method' => 'stripe',
            'processing_attempts' => 0,
            'idempotency_key' => 'custom-payout-key-123',
        ]);

        $transferObj = new class {
            public string $id = 'tr_new_custom_key';
            public string $status = 'paid';

            public function toArray(): array
            {
                return ['id' => 'tr_new_custom_key', 'status' => 'paid'];
            }
        };

        $stripe = m::mock(StripeClient::class);
        $stripe->transfers = m::mock(TransferService::class);

        $stripe->transfers->shouldReceive('create')
            ->once()
            ->with(
                m::on(function (array $payload) use ($payout): bool {
                    return $payload['amount'] === (int) $payout->amount
                        && $payload['currency'] === 'gbp'
                        && $payload['metadata']['payout_id'] === (string) $payout->id;
                }),
                m::on(function (array $options): bool {
                    return $options['idempotency_key'] === 'custom-payout-key-123';
                }),
            )
            ->andReturn($transferObj);

        $job = new ProcessStripePayoutJob($payout->id);
        $job->stripe = $stripe;
        $job->payoutRepository = new PayoutRepository();
        $job->payoutAccountRepository = new ContributorPayoutAccountRepository();
        $job->handle();

        $fresh = Payout::find($payout->id);

        $this->assertEquals('tr_new_custom_key', $fresh->provider_transfer_id);
        $this->assertEquals(1, (int) $fresh->processing_attempts);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}

