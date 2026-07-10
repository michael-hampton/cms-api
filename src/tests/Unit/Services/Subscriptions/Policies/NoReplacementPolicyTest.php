<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Policies;

use App\DTO\Subscriptions\CancellationPolicyContext;
use App\DTO\Subscriptions\PausePolicyContext;
use App\DTO\Subscriptions\PolicyContext;
use App\DTO\Subscriptions\ReplacementUsageStatistics;
use App\Enums\Subscriptions\ReplacementResolution;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Models\IssueDelivery;
use App\Models\ReplacementPolicy;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Subscriptions\Policies\NoReplacementPolicy;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

class NoReplacementPolicyTest extends TestCase
{
    private function makeContext(ReplacementResolution $resolution): PolicyContext
    {
        return new PolicyContext(
            Mockery::mock(Subscription::class)->makePartial(),
            Mockery::mock(SubscriptionPlan::class)->makePartial(),
            Mockery::mock(IssueDelivery::class)->makePartial(),
            $resolution,
            new ReplacementUsageStatistics(0, 0),
            7,
            10,
            0
        );
    }

    private function makeCancellationContext(): CancellationPolicyContext
    {
        return new CancellationPolicyContext(
            subscription: Mockery::mock(Subscription::class)->makePartial(),
            planId: 1,
            reason: null,
            cancellationNotes: null,
            requestedCancellationDate: new DateTimeImmutable(),
            currentStatus: SubscriptionStatus::TRIALING,
            subscriptionAgeDays: 5,
            remainingTermDays: 25,
        );
    }

    private function makePauseContext(): PausePolicyContext
    {
        return new PausePolicyContext(
            subscription: Mockery::mock(Subscription::class)->makePartial(),
            planId: 1,
            requestedPauseDate: new DateTimeImmutable(),
            requestedResumeDate: new DateTimeImmutable('+14 days'),
            currentStatus: SubscriptionStatus::TRIALING,
            subscriptionAgeDays: 5,
            remainingTermDays: 25,
            pausesUsedThisTerm: 0,
        );
    }

    private function makePolicy(): NoReplacementPolicy
    {
        $model = Mockery::mock(ReplacementPolicy::class)->makePartial();
        $model->id = 1;
        $model->name = 'No Replacement';
        $model->active = true;

        return new NoReplacementPolicy($model);
    }

    public function test_it_always_denies_replacement(): void
    {
        $result = $this->makePolicy()->evaluate($this->makeContext(ReplacementResolution::REPLACE));

        $this->assertFalse($result->isAllowed());
        $this->assertSame('This plan does not allow issue replacements.', $result->blockedReason);
    }

    public function test_it_always_denies_extension(): void
    {
        $result = $this->makePolicy()->evaluate($this->makeContext(ReplacementResolution::EXTEND));

        $this->assertFalse($result->isAllowed());
        $this->assertSame('This plan does not allow subscription extensions.', $result->blockedReason);
    }

    public function test_it_always_allows_cancellation(): void
    {
        $result = $this->makePolicy()->evaluateCancellation($this->makeCancellationContext());

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_denies_pause(): void
    {
        $result = $this->makePolicy()->evaluatePause($this->makePauseContext());

        $this->assertFalse($result->isAllowed());
        $this->assertSame('Pausing is not available on this plan.', $result->blockedReason);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
