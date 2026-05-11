<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\OpenCollab;

use App\Models\ContributorPayoutAccount;
use App\Repositories\OpenCollab\ContributorPayoutAccountRepository;
use App\Services\OpenCollab\StripeConnectAccountService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Stripe\Service\AccountLinkService;
use Stripe\Service\AccountService;
use Stripe\StripeClient;

class StripeConnectAccountServiceTest extends FunctionalTestCase
{
    use MockeryPHPUnitIntegration;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function test_existing_account_reused_and_account_create_called_once(): void
    {
        $repo = Mockery::mock(ContributorPayoutAccountRepository::class);
        $stripe = Mockery::mock(StripeClient::class);

        $existing = new ContributorPayoutAccount([
            'user_id' => 123,
            'provider' => 'stripe',
            'stripe_account_id' => 'acct_existing',
        ]);

        $repo->shouldReceive('findByUserId')
            ->once()
            ->with(123, 'stripe')
            ->andReturn($existing);

        $accountService = Mockery::mock(AccountService::class);
        $accountLinkService = Mockery::mock(AccountLinkService::class);
        $stripe->accounts = $accountService;
        $stripe->accountLinks = $accountLinkService;

        $accountService->shouldReceive('create')->never();

        $link = new \stdClass();
        $link->url = 'https://connect.stripe.test/onboard';

        $accountLinkService->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($payload) {
                return $payload['account'] === 'acct_existing'
                    && $payload['type'] === 'account_onboarding';
            }))
            ->andReturn($link);

        $service = new StripeConnectAccountService($repo, $stripe);

        $result = $service->createOrRefreshOnboarding(
            userId: 123,
            returnUrl: 'https://app.test/return',
            refreshUrl: 'https://app.test/refresh',
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('https://connect.stripe.test/onboard', $result['onboarding_url']);
        $this->assertEquals('acct_existing', $result['stripe_account_id']);
    }
}

