<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Model;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\StripeSubscriptionPlanUpdater;
use App\Services\Subscriptions\SubscriptionStripePlanSyncService;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionStripePlanSyncServiceTest extends TestCase
{
    public function test_sync_plan_change_marks_subscription_synced_after_success(): void
    {
        $subscription = self::subscription([
            'id' => 10,
            'stripe_sync_status' => 'pending',
            'payment_subscription_id' => 'sub_123',
            'stripe_subscription_item_id' => 'si_123',
            'stripe_price_id' => 'price_new',
        ]);

        $repository = new FakeSubscriptionRepository($subscription);
        $updater = new FakeStripeSubscriptionPlanUpdater(['success' => true]);

        self::service($repository, $updater)->syncPlanChange(10);

        $this->assertSame(10, $repository->findIds[0]);
        $this->assertSame('si_123', $updater->itemId);
        $this->assertSame('price_new', $updater->priceId);
        $this->assertSame(['proration_behavior' => 'none'], $updater->options);
        $this->assertSame('synced', $repository->updates[0]['data']['stripe_sync_status']);
        $this->assertNull($repository->updates[0]['data']['stripe_sync_error']);
        $this->assertArrayHasKey('stripe_synced_at', $repository->updates[0]['data']);
    }

    public function test_sync_plan_change_marks_subscription_failed_when_stripe_update_fails(): void
    {
        $subscription = self::subscription([
            'id' => 10,
            'stripe_sync_status' => 'pending',
            'payment_subscription_id' => 'sub_123',
            'stripe_subscription_item_id' => 'si_123',
            'stripe_price_id' => 'price_new',
        ]);

        $repository = new FakeSubscriptionRepository($subscription);
        $updater = new FakeStripeSubscriptionPlanUpdater([
            'success' => false,
            'error' => 'Stripe refused this price.',
        ]);

        self::service($repository, $updater)->syncPlanChange(10);

        $this->assertSame(10, $repository->findIds[0]);
        $this->assertSame('si_123', $updater->itemId);
        $this->assertSame('price_new', $updater->priceId);
        $this->assertSame('failed', $repository->updates[0]['data']['stripe_sync_status']);
        $this->assertSame('Stripe refused this price.', $repository->updates[0]['data']['stripe_sync_error']);
    }

    public function test_sync_plan_change_backfills_missing_subscription_item_id(): void
    {
        $subscription = self::subscription([
            'id' => 10,
            'stripe_sync_status' => 'pending',
            'payment_subscription_id' => 'sub_123',
            'stripe_subscription_item_id' => null,
            'stripe_price_id' => 'price_new',
        ]);

        $repository = new FakeSubscriptionRepository($subscription);
        $updater = new FakeStripeSubscriptionPlanUpdater(['success' => true], 'si_resolved');

        self::service($repository, $updater)->syncPlanChange(10);

        $this->assertSame('sub_123', $updater->lookupSubscriptionId);
        $this->assertSame('si_resolved', $updater->itemId);
        $this->assertSame('si_resolved', $repository->updates[0]['data']['stripe_subscription_item_id']);
        $this->assertSame('synced', $repository->updates[1]['data']['stripe_sync_status']);
    }

    public function test_sync_plan_change_resolves_missing_price_from_pricing_repository(): void
    {
        $subscription = self::subscription([
            'id' => 10,
            'plan_id' => 20,
            'subscription_plan_pricing_id' => 30,
            'stripe_sync_status' => 'pending',
            'payment_subscription_id' => 'sub_123',
            'stripe_subscription_item_id' => 'si_123',
            'stripe_price_id' => null,
        ]);

        $pricing = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();
        $pricing->id = 30;
        $pricing->stripe_price_id = 'price_from_repo';

        $repository = new FakeSubscriptionRepository($subscription);
        $updater = new FakeStripeSubscriptionPlanUpdater(['success' => true]);
        $pricingRepository = new FakeSubscriptionPlanPricingRepository($pricing);

        self::service($repository, $updater, $pricingRepository)->syncPlanChange(10);

        $this->assertSame(30, $pricingRepository->findIds[0]);
        $this->assertSame('price_from_repo', $updater->priceId);
        $this->assertSame('price_from_repo', $repository->updates[0]['data']['stripe_price_id']);
        $this->assertSame('synced', $repository->updates[1]['data']['stripe_sync_status']);
    }

    public static function subscription(array $attributes): Subscription
    {
        $subscription = (new \ReflectionClass(Subscription::class))
            ->newInstanceWithoutConstructor();

        foreach ($attributes as $key => $value) {
            $subscription->{$key} = $value;
        }

        return $subscription;
    }

    private static function service(
        FakeSubscriptionRepository $repository,
        FakeStripeSubscriptionPlanUpdater $updater,
        ?FakeSubscriptionPlanPricingRepository $pricingRepository = null,
        ?FakeSubscriptionPlanRepository $planRepository = null,
    ): SubscriptionStripePlanSyncService {
        return new SubscriptionStripePlanSyncService(
            $repository,
            $updater,
            $pricingRepository ?? new FakeSubscriptionPlanPricingRepository(),
            $planRepository ?? new FakeSubscriptionPlanRepository(),
        );
    }
}

final class FakeSubscriptionRepository extends SubscriptionRepository
{
    /** @var array<int, int> */
    public array $findIds = [];

    /** @var array<int, array{id: int, data: array}> */
    public array $updates = [];

    public function __construct(private readonly ?Subscription $subscription) {}

    public function find(int $id, array $relations = []): ?Model
    {
        $this->findIds[] = $id;

        return $this->subscription;
    }

    public function update(int $id, array $data): ?Model
    {
        $this->updates[] = [
            'id' => $id,
            'data' => $data,
        ];

        return $this->subscription;
    }
}

final class FakeSubscriptionPlanPricingRepository extends SubscriptionPlanPricingRepository
{
    /** @var array<int, int> */
    public array $findIds = [];

    public function __construct(
        private readonly ?SubscriptionPlanPricing $pricing = null,
        private readonly ?SubscriptionPlanPricing $defaultPricing = null,
    ) {}

    public function find(int $id, array $relations = []): ?Model
    {
        $this->findIds[] = $id;

        return $this->pricing;
    }

    public function getDefaultForPlan(int $planId): ?SubscriptionPlanPricing
    {
        return $this->defaultPricing;
    }
}

final class FakeSubscriptionPlanRepository extends SubscriptionPlanRepository
{
    public function __construct(private readonly ?SubscriptionPlan $plan = null) {}

    public function find(int $id, array $relations = []): ?Model
    {
        return $this->plan;
    }
}

final class FakeStripeSubscriptionPlanUpdater extends StripeSubscriptionPlanUpdater
{
    public ?string $itemId = null;
    public ?string $priceId = null;
    public ?array $options = null;
    public ?string $lookupSubscriptionId = null;

    public function __construct(
        private readonly array $result,
        private readonly ?string $resolvedItemId = null,
    ) {}

    public function findFirstSubscriptionItemId(string $stripeSubscriptionId): ?string
    {
        $this->lookupSubscriptionId = $stripeSubscriptionId;

        return $this->resolvedItemId;
    }

    public function updateSubscriptionItemPrice(
        string $stripeSubscriptionItemId,
        string $stripePriceId,
        array $options = [],
    ): array {
        $this->itemId = $stripeSubscriptionItemId;
        $this->priceId = $stripePriceId;
        $this->options = $options;

        return $this->result;
    }
}
