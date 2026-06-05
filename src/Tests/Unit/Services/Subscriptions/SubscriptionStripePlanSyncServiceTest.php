<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Model;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\StripeSubscriptionPlanUpdater;
use App\Services\Subscriptions\SubscriptionStripePlanSyncService;
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

        (new SubscriptionStripePlanSyncService($repository, $updater))->syncPlanChange(10);

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

        (new SubscriptionStripePlanSyncService($repository, $updater))->syncPlanChange(10);

        $this->assertSame(10, $repository->findIds[0]);
        $this->assertSame('si_123', $updater->itemId);
        $this->assertSame('price_new', $updater->priceId);
        $this->assertSame('failed', $repository->updates[0]['data']['stripe_sync_status']);
        $this->assertSame('Stripe refused this price.', $repository->updates[0]['data']['stripe_sync_error']);
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

final class FakeStripeSubscriptionPlanUpdater extends StripeSubscriptionPlanUpdater
{
    public ?string $itemId = null;
    public ?string $priceId = null;
    public ?array $options = null;

    public function __construct(private readonly array $result) {}

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
