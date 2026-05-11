<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Models\ContributorPayoutAccount;
use App\Models\User;
use App\Repositories\OpenCollab\ContributorPayoutAccountRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ContributorPayoutAccountRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ContributorPayoutAccountRepository $repository;
    private User $user;

    public function test_find_by_user_id_returns_null_when_missing(): void
    {
        $this->assertNull($this->repository->findByUserId(999));
    }

    public function test_create_persists_correctly(): void
    {
        $account = $this->repository->create([
            'user_id' => $this->user->id,
            'provider' => 'stripe',
            'stripe_account_id' => 'acct_123',
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'details_submitted' => false,
            'requirements_due_json' => ['currently_due' => ['external_account']],
        ]);

        $this->assertInstanceOf(ContributorPayoutAccount::class, $account);
        $this->assertEquals($this->user->id, $account->user_id);
        $this->assertEquals('stripe', $account->provider);
        $this->assertEquals('acct_123', $account->stripe_account_id);
        $this->assertIsArray($account->requirements_due_json);
        $this->assertEquals(['currently_due' => ['external_account']], $account->requirements_due_json);

        $this->assertDatabaseHas('contributor_payout_accounts', [
            'user_id' => $this->user->id,
            'provider' => 'stripe',
            'stripe_account_id' => 'acct_123',
        ]);
    }

    public function test_find_by_stripe_account_id_returns_account(): void
    {
        ContributorPayoutAccount::create([
            'user_id' => $this->user->id,
            'provider' => 'stripe',
            'stripe_account_id' => 'acct_abc',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
        ]);

        $found = $this->repository->findByStripeAccountId('acct_abc');

        $this->assertNotNull($found);
        $this->assertEquals($this->user->id, $found->user_id);
    }

    public function test_update_capabilities_updates_only_intended_fields(): void
    {
        $account = ContributorPayoutAccount::create([
            'user_id' => $this->user->id,
            'provider' => 'stripe',
            'stripe_account_id' => 'acct_keep',
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'details_submitted' => false,
            'requirements_due_json' => ['a' => 1],
        ]);

        $updated = $this->repository->updateCapabilities($account->id, [
            'payouts_enabled' => true,
            'details_submitted' => true,
            'requirements_due_json' => ['b' => 2],
            // should be ignored by updateCapabilities()
            'stripe_account_id' => 'acct_changed',
            'provider' => 'other',
        ]);

        $this->assertNotNull($updated);
        $fresh = ContributorPayoutAccount::find($account->id);

        $this->assertEquals('acct_keep', $fresh->stripe_account_id);
        $this->assertEquals('stripe', $fresh->provider);
        $this->assertTrue($fresh->payouts_enabled);
        $this->assertTrue($fresh->details_submitted);
        $this->assertEquals(['b' => 2], $fresh->requirements_due_json);
    }

    public function test_duplicate_stripe_account_ids_fail(): void
    {
        ContributorPayoutAccount::create([
            'user_id' => $this->user->id,
            'provider' => 'stripe',
            'stripe_account_id' => 'acct_dupe',
        ]);

        $this->expectException(\Exception::class);

        ContributorPayoutAccount::create([
            'user_id' => $this->createUser()->id,
            'provider' => 'stripe',
            'stripe_account_id' => 'acct_dupe',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ContributorPayoutAccountRepository();
        $this->user = $this->createUser();
    }
}

