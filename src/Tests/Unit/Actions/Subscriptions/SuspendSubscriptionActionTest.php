<?php

declare(strict_types=1);

namespace App\Tests\Unit\Actions\Subscriptions;

use App\Actions\Subscriptions\SuspendSubscriptionAction;
use App\DTO\Subscriptions\BusinessDecisions\ResolvedSuspensionOptions;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Framework\Database\Database;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\BusinessDecisions\SuspensionOptionsResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

class SuspendSubscriptionActionTest extends TestCase
{
    private $subscriptionRepository;
    private $database;
    private $suspensionOptionsResolver;
    private SuspendSubscriptionAction $action;

    public function test_reason_is_required(): void
    {
        $sub = $this->makeSubscription();

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn($sub);

        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(
            1,
            99,
            1,
            '   ',
            10
        );
    }

    public function test_reason_not_required_when_business_decision_says_so(): void
    {
        $sub = $this->makeSubscription();

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn($sub);

        $this->suspensionOptionsResolver
            ->shouldReceive('resolveForPlan')
            ->andReturn(new ResolvedSuspensionOptions(allowSuspend: true, requiresNote: false));

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->subscriptionRepository->shouldReceive('update')->once()->andReturn(null);
        $this->subscriptionRepository->shouldReceive('revokeAllPremiumAccess')->once();

        $result = $this->action->execute(1, 99, 1, '', 10);

        $this->assertSame($sub, $result);
    }

    public function test_suspension_blocked_by_business_decision(): void
    {
        $sub = $this->makeSubscription();

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn($sub);

        $this->suspensionOptionsResolver
            ->shouldReceive('resolveForPlan')
            ->andReturn(new ResolvedSuspensionOptions(allowSuspend: false, requiresNote: true));

        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(1, 99, 1, 'reason', 10);
    }

    public function test_subscription_not_found(): void
    {
        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(
            1,
            99,
            1,
            'reason',
            10
        );
    }

    public function test_site_mismatch(): void
    {
        $sub = $this->makeSubscription();
        $sub->site_id = 999;

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn($sub);

        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(
            1,
            99,
            1,
            'reason',
            10
        );
    }

    private function makeSubscription(string $status = 'active'): object
    {
        $sub = Mockery::mock(Subscription::class)->makePartial();

        $sub->id = 1;
        $sub->site_id = 10;
        $sub->member_id = 99;
        $sub->status = $status;

        return $sub;
    }

    public function test_member_mismatch(): void
    {
        $sub = $this->makeSubscription();
        $sub->member_id = 123;

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn($sub);

        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(
            1,
            99,
            1,
            'reason',
            10
        );
    }

    public function test_already_suspended(): void
    {
        $sub = $this->makeSubscription(SubscriptionStatus::SUSPENDED->value);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn($sub);

        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(
            1,
            99,
            1,
            'reason',
            10
        );
    }

    public function test_non_suspendable_status_rejected(): void
    {
        $sub = $this->makeSubscription('expired');

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn($sub);

        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(
            1,
            99,
            1,
            'reason',
            10
        );
    }

    public function test_successful_suspension_flow(): void
    {
        $sub = $this->makeSubscription();

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($sub);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn(null);

        $this->subscriptionRepository
            ->shouldReceive('revokeAllPremiumAccess')
            ->once();

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($sub);

        $result = $this->action->execute(
            1,
            99,
            1,
            'Violation of terms',
            10
        );

        $this->assertSame($sub, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->database = Mockery::mock(Database::class);

        // Default: matches the resolver's own unconfigured fallback
        // (allow_suspend=true, requires_note=true) so pre-existing tests
        // written before this governance layer existed are unaffected.
        $this->suspensionOptionsResolver = Mockery::mock(SuspensionOptionsResolver::class);
        $this->suspensionOptionsResolver
            ->shouldReceive('resolveForPlan')
            ->andReturn(new ResolvedSuspensionOptions(allowSuspend: true, requiresNote: true))
            ->byDefault();

        $this->action = new SuspendSubscriptionAction(
            $this->subscriptionRepository,
            $this->database,
            $this->suspensionOptionsResolver,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}