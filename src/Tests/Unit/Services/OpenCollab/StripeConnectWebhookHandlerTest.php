<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\PayoutStatus;
use App\Framework\Support\Logger;
use App\Models\ContributorPayoutAccount;
use App\Models\Payout;
use App\Repositories\OpenCollab\ContributorPayoutAccountRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Services\OpenCollab\StripeConnectWebhookHandler;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new StripeConnectWebhookHandler(
            new ContributorPayoutAccountRepository(),
            new PayoutRepository(),
            new Logger(),
        );
    }
}

