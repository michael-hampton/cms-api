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
use App\Services\Subscriptions\Policies\StandardConsumerPolicy;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

class StandardConsumerPolicyTest extends TestCase
{
    private function makeContext(ReplacementResolution $resolution, ReplacementUsageStatistics $usage): PolicyContext
    {
        return new PolicyContext(
            Mockery::mock(Subscription::class)->makePartial(),
            Mockery::mock(SubscriptionPlan::class)->makePartial(),
            Mockery::mock(IssueDelivery::class)->makePartial(),
            $resolution,
            $usage,
            7,
            10,
            0
        );
    }

    private function makeCancellationContext(): CancellationPolicyContext
    {
        return new CancellationPolicyContext(
            subscription: Mockery::mock(Subscription::class)->makePartial(),
            planId: 2,
            reason: null,
            cancellationNotes: null,
            requestedCancellationDate: new DateTimeImmutable(),
            currentStatus: SubscriptionStatus::ACTIVE,
            subscriptionAgeDays: 30,
            remainingTermDays: 10,
        );
    }

    private function makePauseContext(int $pausesUsedThisTerm): PausePolicyContext
    {
        return new PausePolicyContext(
            subscription: Mockery::mock(Subscription::class)->makePartial(),
            planId: 2,
            requestedPauseDate: new DateTimeImmutable(),
            requestedResumeDate: new DateTimeImmutable('+14 days'),
            currentStatus: SubscriptionStatus::ACTIVE,
            subscriptionAgeDays: 30,
            remainingTermDays: 10,
            pausesUsedThisTerm: $pausesUsedThisTerm,
        );
    }

    private function makePolicy(): StandardConsumerPolicy
    {
        $model = Mockery::mock(ReplacementPolicy::class)->makePartial();
        $model->id = 2;
        $model->name = 'Standard Consumer';
        $model->active = true;

        return new StandardConsumerPolicy($model);
    }

    public function test_it_allows_replacement_within_the_limit(): void
    {
        $result = $this->makePolicy()->evaluate(
            $this->makeContext(ReplacementResolution::REPLACE, new ReplacementUsageStatistics(1, 0))
        );

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_requires_business_override_once_the_replacement_limit_is_reached(): void
    {
        $result = $this->makePolicy()->evaluate(
            $this->makeContext(ReplacementResolution::REPLACE, new ReplacementUsageStatistics(2, 0))
        );

        $this->assertFalse($result->isAllowed());
        $this->assertSame('The replacement limit for this plan has been reached.', $result->blockedReason);
    }

    public function test_it_allows_extension_within_the_limit(): void
    {
        $result = $this->makePolicy()->evaluate(
            $this->makeContext(ReplacementResolution::EXTEND, new ReplacementUsageStatistics(0, 1))
        );

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_requires_business_override_once_the_extension_limit_is_reached(): void
    {
        $result = $this->makePolicy()->evaluate(
            $this->makeContext(ReplacementResolution::EXTEND, new ReplacementUsageStatistics(0, 2))
        );

        $this->assertFalse($result->isAllowed());
        $this->assertSame('The extension limit for this plan has been reached.', $result->blockedReason);
    }

    public function test_it_always_allows_cancellation(): void
    {
        $result = $this->makePolicy()->evaluateCancellation($this->makeCancellationContext());

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_allows_a_pause_when_none_used_this_term(): void
    {
        $result = $this->makePolicy()->evaluatePause($this->makePauseContext(0));

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_denies_a_second_pause_within_the_same_term(): void
    {
        $result = $this->makePolicy()->evaluatePause($this->makePauseContext(1));

        $this->assertFalse($result->isAllowed());
        $this->assertSame(
            'This plan allows one pause per subscription term, which has already been used.',
            $result->blockedReason
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
