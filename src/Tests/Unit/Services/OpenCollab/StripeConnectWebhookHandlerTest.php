<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\PayoutStatus;
use App\Framework\Support\Logger;
use App\Models\ContributorPayoutAccount;
use App\Models\Payout;
use App\Models\Site;
use App\Repositories\Cms\SiteRepository;
use App\Repositories\OpenCollab\ContributorPayoutAccountRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Services\OpenCollab\ContributorOnboardingService;
use App\Services\OpenCollab\PayoutLedgerService;
use App\Services\OpenCollab\StripeConnectWebhookHandler;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class StripeConnectWebhookHandlerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private StripeConnectWebhookHandler $handler;

    public function test_account_updated_maps_capability_fields_correctly(): void
    {
        $user = $this->createUser();
        $account = ContributorPayoutAccount::create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'stripe_account_id' => 'acct_connect_1',
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'details_submitted' => false,
            'requirements_due_json' => ['foo'],
        ]);

        $event = (object)[
            'id' => 'evt_acc_updated',
            'type' => 'account.updated',
            'data' => (object)[
                'object' => (object)[
                    'id' => 'acct_connect_1',
                    'charges_enabled' => true,
                    'payouts_enabled' => true,
                    'details_submitted' => true,
                    'requirements' => (object)['currently_due' => []],
                ],
            ],
        ];

        $this->handler->handle($event, 'corr_1');

        $fresh = ContributorPayoutAccount::find($account->id);
        $this->assertTrue((bool)$fresh->charges_enabled);
        $this->assertTrue((bool)$fresh->payouts_enabled);
        $this->assertTrue((bool)$fresh->details_submitted);
        $this->assertEquals([], $fresh->requirements_due_json);
    }

    public function test_payout_failed_updates_payout_state(): void
    {
        $payout = Payout::create([
            'user_id' => $this->createUser()->id,
            'site_id' => $this->siteId,
            'amount' => 5000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Approved->value,
            'method' => 'stripe',
            'provider_transfer_id' => 'tr_test_fail',
        ]);

        $event = (object)[
            'id' => 'evt_payout_failed',
            'type' => 'payout.failed',
            'data' => (object)[
                'object' => (object)[
                    'id' => 'po_123',
                    'source_transfer' => 'tr_test_fail',
                    'failure_message' => 'Insufficient funds',
                ],
            ],
        ];

        $this->handler->handle($event, 'corr_fail');

        $fresh = Payout::find($payout->id);
        $this->assertEquals(PayoutStatus::Failed->value, $fresh->status);
    }

    public function test_payout_paid_updates_payout_state(): void
    {
        $payout = Payout::create([
            'user_id' => $this->createUser()->id,
            'site_id' => $this->siteId,
            'amount' => 5000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Approved->value,
            'method' => 'stripe',
            'provider_transfer_id' => 'tr_test_paid',
        ]);

        $event = (object)[
            'id' => 'evt_payout_paid',
            'type' => 'payout.paid',
            'data' => (object)[
                'object' => (object)[
                    'id' => 'po_789',
                    'source_transfer' => 'tr_test_paid',
                ],
            ],
        ];

        $this->handler->handle($event, 'corr_paid');

        $fresh = Payout::find($payout->id);
        $this->assertEquals(PayoutStatus::Paid->value, $fresh->status);
    }

    public function test_account_updated_restricted_invalidates_kyc_and_syncs_onboarding(): void
    {
        $user = $this->createUser();
        ContributorPayoutAccount::create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'stripe_account_id' => 'acct_restricted',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
            'requirements_due_json' => [],
        ]);

        $site = new Site([
            'id' => $this->siteId,
            'require_kyc_verification' => true,
        ]);
        $site->exists = true;

        $onboarding = Mockery::mock(ContributorOnboardingService::class);
        $onboarding->shouldReceive('invalidateStep')->once()->with($user->id, $this->siteId, 'kyc_verification');
        $onboarding->shouldReceive('syncStatus')->once()->with($user->id, $site);

        $siteRepository = Mockery::mock(SiteRepository::class);
        $siteRepository->shouldReceive('findSitesForContributor')->once()->with($user->id)->andReturn([$site]);

        $handler = new StripeConnectWebhookHandler(
            new ContributorPayoutAccountRepository(),
            new PayoutRepository(),
            new Logger(),
            $onboarding,
            $siteRepository,
            Mockery::mock(PayoutLedgerService::class),
        );

        $handler->handle((object)[
            'id' => 'evt_acc_restricted',
            'type' => 'account.updated',
            'data' => (object)[
                'object' => (object)[
                    'id' => 'acct_restricted',
                    'charges_enabled' => true,
                    'payouts_enabled' => false,
                    'details_submitted' => true,
                    'requirements' => (object)['currently_due' => ['individual.verification.document']],
                ],
            ],
        ], 'corr_restricted');

        $fresh = ContributorPayoutAccount::where('stripe_account_id', 'acct_restricted')->first();
        $this->assertFalse((bool)$fresh->payouts_enabled);
    }

    public function test_payout_paid_marks_linked_ledger_entries_withdrawn(): void
    {
        $payout = Payout::create([
            'user_id' => $this->createUser()->id,
            'site_id' => $this->siteId,
            'amount' => 5000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Approved->value,
            'method' => 'stripe',
            'provider_transfer_id' => 'tr_test_withdraw',
        ]);

        $ledger = Mockery::mock(PayoutLedgerService::class);
        $ledger->shouldReceive('markPayoutLedgerEntriesWithdrawn')->once()->with((int)$payout->id);

        $handler = new StripeConnectWebhookHandler(
            new ContributorPayoutAccountRepository(),
            new PayoutRepository(),
            new Logger(),
            Mockery::mock(ContributorOnboardingService::class),
            Mockery::mock(SiteRepository::class),
            $ledger,
        );

        $handler->handle((object)[
            'id' => 'evt_payout_paid_withdraw',
            'type' => 'payout.paid',
            'data' => (object)[
                'object' => (object)[
                    'id' => 'po_withdraw',
                    'source_transfer' => 'tr_test_withdraw',
                ],
            ],
        ], 'corr_paid_withdraw');

        $this->assertSame(PayoutStatus::Paid->value, Payout::find($payout->id)->status);
    }

    public function test_payout_failed_does_not_demote_already_paid_payout(): void
    {
        $payout = Payout::create([
            'user_id' => $this->createUser()->id,
            'site_id' => $this->siteId,
            'amount' => 5000,
            'currency' => 'GBP',
            'status' => PayoutStatus::Paid->value,
            'method' => 'stripe',
            'provider_transfer_id' => 'tr_already_paid',
            'processed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->handler->handle((object)[
            'id' => 'evt_payout_failed_late',
            'type' => 'payout.failed',
            'data' => (object)[
                'object' => (object)[
                    'id' => 'po_late_fail',
                    'source_transfer' => 'tr_already_paid',
                    'failure_message' => 'Late failure event',
                ],
            ],
        ], 'corr_late_fail');

        $fresh = Payout::find($payout->id);
        $this->assertEquals(PayoutStatus::Paid->value, $fresh->status);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $siteRepository = Mockery::mock(SiteRepository::class);
        $siteRepository->shouldReceive('findSitesForContributor')->andReturn([])->byDefault();

        $this->handler = new StripeConnectWebhookHandler(
            new ContributorPayoutAccountRepository(),
            new PayoutRepository(),
            new Logger(),
            Mockery::mock(ContributorOnboardingService::class)->shouldIgnoreMissing(),
            $siteRepository,
            Mockery::mock(PayoutLedgerService::class)->shouldIgnoreMissing(),
        );
    }
}
