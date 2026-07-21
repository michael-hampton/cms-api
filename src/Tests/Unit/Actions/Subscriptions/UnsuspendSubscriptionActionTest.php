<?php

declare(strict_types=1);

namespace App\Tests\Unit\Actions\Subscriptions;

use App\Actions\Subscriptions\UnsuspendSubscriptionAction;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\MemberAccessService;
use Mockery;
use PHPUnit\Framework\TestCase;

class UnsuspendSubscriptionActionTest extends TestCase
{
    private $subscriptionRepository;
    private $accessService;
    private $database;
    private UnsuspendSubscriptionAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->accessService = Mockery::mock(MemberAccessService::class);
        $this->database = Mockery::mock(Database::class);

        $this->action = new UnsuspendSubscriptionAction(
            $this->subscriptionRepository,
            $this->accessService,
            $this->database,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeSubscription(string $status = 'suspended'): object
    {
        $sub = Mockery::mock(Subscription::class)->makePartial();

        $sub->id = 1;
        $sub->site_id = 10;
        $sub->member_id = 99;
        $sub->status = $status;
        $sub->end_date = null;

        return $sub;
    }

    public function test_subscription_not_found(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(1, 99, 1, 'reason', 10);
    }

    public function test_site_mismatch(): void
    {
        $sub = $this->makeSubscription();
        $sub->site_id = 999;

        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(1, 99, 1, 'reason', 10);
    }

    public function test_member_mismatch(): void
    {
        $sub = $this->makeSubscription();
        $sub->member_id = 123;

        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(1, 99, 1, 'reason', 10);
    }

    public function test_not_currently_suspended_is_rejected(): void
    {
        $sub = $this->makeSubscription(SubscriptionStatus::ACTIVE->value);

        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription is not suspended.');

        $this->action->execute(1, 99, 1, 'reason', 10);
    }

    public function test_expired_entitlement_period_is_rejected(): void
    {
        $sub = $this->makeSubscription();
        $sub->end_date = new \DateTime('-1 day');

        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription entitlement period has ended. Please purchase a new subscription.');

        $this->action->execute(1, 99, 1, 'reason', 10);
    }

    public function test_successful_unsuspend_flow_without_end_date(): void
    {
        $sub = $this->makeSubscription();

        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($sub);

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn ($callback) => $callback());

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(1, [
                'status' => SubscriptionStatus::ACTIVE->value,
                'suspended_at' => null,
                'auto_renew' => true,
            ])
            ->andReturn(null);

        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($sub);

        $this->accessService->shouldReceive('refreshSubscriptionAccess')->never();

        $result = $this->action->execute(1, 99, 1, 'Payment resolved', 10);

        $this->assertSame($sub, $result);
    }

    public function test_successful_unsuspend_flow_refreshes_access_when_end_date_present(): void
    {
        $sub = $this->makeSubscription();
        $sub->end_date = new \DateTime('+30 days');

        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($sub);

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn ($callback) => $callback());

        $this->subscriptionRepository->shouldReceive('update')->once()->andReturn(null);

        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($sub);

        $this->accessService->shouldReceive('refreshSubscriptionAccess')
            ->once()
            ->with($sub, Mockery::type(\DateTimeImmutable::class));

        $result = $this->action->execute(1, 99, 1, null, 10);

        $this->assertSame($sub, $result);
    }
}
