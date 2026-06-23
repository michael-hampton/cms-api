<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Container;
use App\Framework\Http\Response;
use App\Framework\Http\TestResponse;
use App\Models\Member;
use App\Models\Subscription;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Members\AddressRepository;
use App\Repositories\Subscriptions\PlanIssueScheduleRepository;
use App\Repositories\Subscriptions\SubscriptionAccountModalPlanRepository;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Order\OrderManager;
use App\Services\Billing\Order\OrderUpdateService;
use App\Services\Billing\Stripe\StripeCustomerPaymentMethodService;
use App\Services\Subscriptions\MemberSubscriptionService;
use App\Services\Subscriptions\SubscriptionAccountPageProvider;
use App\Services\Subscriptions\SubscriptionBillingService;
use App\Services\Subscriptions\SubscriptionCancellationService;
use App\Services\Subscriptions\SubscriptionDeliveryService;
use App\Services\Subscriptions\SubscriptionHistoryService;
use App\Services\Subscriptions\SubscriptionListingService;
use App\Services\Subscriptions\SubscriptionPauseService;
use App\Services\Subscriptions\SubscriptionPaymentRecoveryService;
use App\Services\Subscriptions\SubscriptionUpgradeService;
use App\Framework\Authorization\AuthenticationService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;
use stdClass;

class ShopAccountControllersTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember([
            'email' => 'shop-account-member@example.com',
            'first_name' => 'Shop',
            'last_name' => 'Member',
            'status' => 'active',
        ]);

        $this->actingAsMember($this->member);
        $this->registerDefaultAccountMocks();
    }

    public function testShopAccountRenewRedirectsOwnedRenewableSubscriptionToCheckout(): void
    {
        $subscription = $this->memberSubscription();
        $this->mockSubscriptionRepositoryReturning($subscription);

        $listingService = Mockery::mock(SubscriptionListingService::class);
        $listingService->shouldReceive('formatSubscriptionForListing')
            ->once()
            ->with(Mockery::on(fn ($given) => (int) $given->id === (int) $subscription->id))
            ->andReturn([
                'actions' => [
                    ['key' => 'renew', 'label' => 'Renew'],
                ],
            ]);
        Container::getInstance()->instance(SubscriptionListingService::class, $listingService);

        $response = $this->getAccount("/press-stack/account/subscriptions/{$subscription->id}/renew");

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/checkout?subscription_id=' . $subscription->id . '&renewal=true', $response->getHeader('Location') ?? '');
    }

    public function testShopAccountApiCancelSubscriptionRequiresValidCancellationReason(): void
    {
        $subscription = $this->memberSubscription();
        $this->mockSubscriptionRepositoryReturning($subscription);

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/cancel", [
            'reason' => 'not-a-valid-reason',
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($this->json($response)['success']);
        $this->assertSame('Please select a cancellation reason.', $this->json($response)['message']);
    }

    public function testBillingDatePreviewReturnsPreviewForOwnedSubscription(): void
    {
        $subscription = $this->memberSubscription();
        $this->mockSubscriptionRepositoryReturning($subscription);

        $billingService = Mockery::mock(SubscriptionBillingService::class);
        $billingService->shouldReceive('previewBillingDateChange')
            ->once()
            ->with((int) $subscription->id, 15)
            ->andReturn([
                'success' => true,
                'current_billing_date' => '2026-07-01',
                'new_billing_date' => '2026-07-15',
            ]);
        Container::getInstance()->instance(SubscriptionBillingService::class, $billingService);

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/billing-date/preview", [
            'day_of_month' => '15',
        ]);

        $data = $this->json($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('2026-07-15', $data['preview']['new_billing_date']);
    }

    public function testBillingDateUpdateRejectsDayOutsideCalendarRange(): void
    {
        $subscription = $this->memberSubscription();
        $this->mockSubscriptionRepositoryReturning($subscription);

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/billing-date", [
            'day_of_month' => '32',
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Please select a day between 1 and 31.', $this->json($response)['message']);
    }

    public function testDeliveryPauseRequiresStartAndEndDates(): void
    {
        $subscription = $this->memberSubscription([
            'delivery_type' => SubscriptionType::PRINTED->value,
        ]);
        $this->mockSubscriptionRepositoryReturning($subscription);

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/delivery/pause", [
            'pause_start' => '2026-08-01',
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Pause start and end dates are required.', $this->json($response)['message']);
    }

    public function testSubscriptionSettingsCanUpdateAutoRenew(): void
    {
        $subscription = $this->memberSubscription();

        $subscriptionService = Mockery::mock(MemberSubscriptionService::class);
        $subscriptionService->shouldReceive('updateAutoRenew')
            ->once()
            ->with((int) $subscription->id, (int) $this->member->id, false, true)
            ->andReturn(['auto_renew' => false]);
        Container::getInstance()->instance(MemberSubscriptionService::class, $subscriptionService);

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/auto-renew", [
            'auto_renew' => '0',
            'consent_given' => '1',
        ]);

        $data = $this->json($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertFalse($data['auto_renew']);
        $this->assertSame('Auto-renewal disabled.', $data['message']);
    }

    public function testSubscriptionHistoryReturnsPaginatedEventsAndCapsPerPage(): void
    {
        $subscription = $this->memberSubscription();
        $this->mockSubscriptionRepositoryReturning($subscription);

        $historyService = Mockery::mock(SubscriptionHistoryService::class);
        $historyService->shouldReceive('getPaginatedHistory')
            ->once()
            ->with((int) $subscription->id, 2, 50)
            ->andReturn([
                'events' => [
                    ['type' => 'created', 'label' => 'Subscription created'],
                ],
                'total' => 125,
            ]);
        Container::getInstance()->instance(SubscriptionHistoryService::class, $historyService);

        $response = $this->getAccount("/press-stack/account/subscriptions/{$subscription->id}/history?page=2&per_page=99");
        $data = $this->json($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame(50, $data['pagination']['per_page']);
        $this->assertTrue($data['pagination']['has_more']);
        $this->assertSame('created', $data['events'][0]['type']);
    }

    public function testSubscriptionPreferenceShowReturnsMemberPreferences(): void
    {
        $subscription = $this->memberSubscription();
        $this->mockSubscriptionRepositoryReturning($subscription);

        $subscriptionService = Mockery::mock(MemberSubscriptionService::class);
        $subscriptionService->shouldReceive('getSubscriptionSummary')
            ->once()
            ->with((int) $this->member->id, (int) $subscription->site_id)
            ->andReturn([
                'is_active' => true,
                'email_notifications' => true,
                'frequency' => 'weekly',
                'content_types' => ['magazine'],
                'category_preferences' => ['business'],
            ]);
        Container::getInstance()->instance(MemberSubscriptionService::class, $subscriptionService);

        $response = $this->getAccount("/press-stack/account/subscriptions/{$subscription->id}/preferences");
        $data = $this->json($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('weekly', $data['preferences']['newsletter_frequency']);
        $this->assertSame(['business'], $data['preferences']['category_preferences']);
    }

    public function testDeliveryAddressIndexRejectsDigitalSubscriptions(): void
    {
        $subscription = $this->memberSubscription([
            'delivery_type' => SubscriptionType::DIGITAL->value,
        ]);
        $this->mockSubscriptionRepositoryReturning($subscription);

        $response = $this->getAccount("/press-stack/account/subscriptions/{$subscription->id}/delivery-addresses");

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Subscription not found.', $this->json($response)['message']);
    }

    public function testIssueDeliveriesRejectDigitalSubscriptions(): void
    {
        $subscription = $this->memberSubscription([
            'delivery_type' => SubscriptionType::DIGITAL->value,
        ]);
        $this->mockSubscriptionRepositoryReturning($subscription);

        $response = $this->getAccount("/press-stack/account/subscriptions/{$subscription->id}/issue-deliveries");

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Subscription not found.', $this->json($response)['message']);
    }

    public function testSubscriptionUpgradePreviewRequiresValidUpgradePlan(): void
    {
        $subscription = $this->memberSubscription();
        $this->mockSubscriptionRepositoryReturning($subscription);

        $response = $this->postAccount("/press-stack/account/subscriptions/{$subscription->id}/upgrades/preview", [
            'upgrade_plan_id' => '0',
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('A valid upgrade plan is required.', $this->json($response)['message']);
    }

    private function registerDefaultAccountMocks(): void
    {
        $container = Container::getInstance();

        foreach ([
            AuthenticationService::class,
            OrderManager::class,
            OrderRepository::class,
            OrderUpdateService::class,
            AddressRepository::class,
            PlanIssueScheduleRepository::class,
            SubscriptionAccountModalPlanRepository::class,
            SubscriptionAccountPageProvider::class,
            SubscriptionBillingService::class,
            SubscriptionCancellationService::class,
            SubscriptionDeliveryService::class,
            SubscriptionHistoryService::class,
            SubscriptionIssueFulfilmentRepository::class,
            SubscriptionListingService::class,
            SubscriptionPauseService::class,
            SubscriptionPaymentRecoveryService::class,
            SubscriptionRepository::class,
            SubscriptionUpgradeService::class,
            StripeCustomerPaymentMethodService::class,
            MemberSubscriptionService::class,
        ] as $class) {
            $container->instance($class, Mockery::mock($class)->shouldIgnoreMissing());
        }
    }

    private function memberSubscription(array $overrides = []): Subscription
    {
        $plan = $this->createSubscriptionPlan();

        return $this->createSubscription(array_merge([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'delivery_type' => SubscriptionType::DIGITAL->value,
            'price' => 10.00,
            'currency' => 'GBP',
            'auto_renew' => true,
            'start_date' => now_datetime(),
            'next_billing_date' => now_datetime()->modify('+1 month'),
        ], $overrides));
    }

    private function mockSubscriptionRepositoryReturning(?Subscription $subscription): void
    {
        $repository = Mockery::mock(SubscriptionRepository::class);
        $repository->shouldReceive('find')
            ->byDefault()
            ->andReturnUsing(function (int $id) use ($subscription) {
                return $subscription && (int) $subscription->id === $id ? $subscription : null;
            });

        Container::getInstance()->instance(SubscriptionRepository::class, $repository);
    }

    private function getAccount(string $uri): Response
    {
        return $this->makeRequest('GET', $uri, [], $this->getDefaultHeaders(['Accept' => 'application/json'], true));
    }

    private function postAccount(string $uri, array $data = []): Response
    {
        return new TestResponse(
            ...array_values([
                'content' => ($response = $this->makeRequest('POST', $uri, $data, $this->getDefaultHeaders(['Accept' => 'application/json'], true)))->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
            ])
        );
    }

    private function json(Response $response): array
    {
        $decoded = json_decode($response->getContent(), true);

        $this->assertIsArray($decoded, 'Expected controller response to contain JSON. Body: ' . $response->getContent());

        return $decoded;
    }
}
