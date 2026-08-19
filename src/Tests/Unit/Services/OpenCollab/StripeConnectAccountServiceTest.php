<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\OpenCollab;

use App\Models\ContributorPayoutAccount;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\ContributorPayoutAccountRepository;
use App\Services\OpenCollab\StripeConnectAccountService;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Stripe\Service\AccountLinkService;
use Stripe\Service\AccountService;
use Stripe\StripeClient;

class StripeConnectAccountServiceTest extends UnitTestCase
{


    public function test_existing_account_reused_and_account_create_called_once(): void
    {
        $repo = Mockery::mock(ContributorPayoutAccountRepository::class);
        $userRepository = Mockery::mock(UserRepositoryInterface::class);
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

        $service = new StripeConnectAccountService($repo, $userRepository, $stripe);

        $result = $service->createOrRefreshOnboarding(
            userId: 123,
            returnUrl: 'https://app.test/return',
            refreshUrl: 'https://app.test/refresh',
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('https://connect.stripe.test/onboard', $result['onboarding_url']);
        $this->assertEquals('acct_existing', $result['stripe_account_id']);
    }

    public function test_new_account_looks_up_user_via_repository_not_static_call(): void
    {
        $repo = Mockery::mock(ContributorPayoutAccountRepository::class);
        $userRepository = Mockery::mock(UserRepositoryInterface::class);
        $stripe = Mockery::mock(StripeClient::class);

        $repo->shouldReceive('findByUserId')
            ->once()
            ->with(456, 'stripe')
            ->andReturn(null);

        $user = Mockery::mock(\App\Models\User::class)->makePartial();
        $user->email = 'contributor@example.com';

        $userRepository->shouldReceive('find')
            ->once()
            ->with(456)
            ->andReturn($user);

        $accountService = Mockery::mock(AccountService::class);
        $accountLinkService = Mockery::mock(AccountLinkService::class);
        $stripe->accounts = $accountService;
        $stripe->accountLinks = $accountLinkService;

        $account = new \stdClass();
        $account->id = 'acct_new';
        $account->charges_enabled = false;
        $account->payouts_enabled = false;
        $account->details_submitted = false;
        $account->requirements = null;

        $accountService->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($payload) => $payload['email'] === 'contributor@example.com'))
            ->andReturn($account);

        $repo->shouldReceive('create')->once()->andReturn(
            new ContributorPayoutAccount(['id' => 1, 'user_id' => 456, 'stripe_account_id' => 'acct_new'])
        );

        $link = new \stdClass();
        $link->url = 'https://connect.stripe.test/onboard-new';

        $accountLinkService->shouldReceive('create')->once()->andReturn($link);

        $service = new StripeConnectAccountService($repo, $userRepository, $stripe);

        $result = $service->createOrRefreshOnboarding(
            userId: 456,
            returnUrl: 'https://app.test/return',
            refreshUrl: 'https://app.test/refresh',
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('acct_new', $result['stripe_account_id']);
    }
}

