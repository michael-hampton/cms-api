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
use App\Services\Subscriptions\Policies\DigitalOnlyPolicy;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

class DigitalOnlyPolicyTest extends TestCase
{
    private function makeContext(ReplacementResolution $resolution, int $extensionsUsed = 0): PolicyContext
    {
        return new PolicyContext(
            Mockery::mock(Subscription::class)->makePartial(),
            Mockery::mock(SubscriptionPlan::class)->makePartial(),
            Mockery::mock(IssueDelivery::class)->makePartial(),
            $resolution,
            new ReplacementUsageStatistics(0, $extensionsUsed),
            7,
            10,
            0
        );
    }

    private function makeCancellationContext(): CancellationPolicyContext
    {
        return new CancellationPolicyContext(
            subscription: Mockery::mock(Subscription::class)->makePartial(),
            planId: 5,
            reason: null,
            cancellationNotes: null,
            requestedCancellationDate: new DateTimeImmutable(),
            currentStatus: SubscriptionStatus::ACTIVE,
            subscriptionAgeDays: 90,
            remainingTermDays: 30,
        );
    }

    private function makePauseContext(int $pausesUsedThisTerm): PausePolicyContext
    {
        return new PausePolicyContext(
            subscription: Mockery::mock(Subscription::class)->makePartial(),
            planId: 5,
            requestedPauseDate: new DateTimeImmutable(),
            requestedResumeDate: new DateTimeImmutable('+14 days'),
            currentStatus: SubscriptionStatus::ACTIVE,
            subscriptionAgeDays: 90,
            remainingTermDays: 30,
            pausesUsedThisTerm: $pausesUsedThisTerm,
        );
    }

    private function makePolicy(): DigitalOnlyPolicy
    {
        $model = Mockery::mock(ReplacementPolicy::class)->makePartial();
        $model->id = 5;
        $model->name = 'Digital Only';
        $model->active = true;

        return new DigitalOnlyPolicy($model);
    }

    public function test_it_denies_physical_replacement_always(): void
    {
        $result = $this->makePolicy()->evaluate($this->makeContext(ReplacementResolution::REPLACE));

        $this->assertFalse($result->isAllowed());
        $this->assertSame('This plan does not support physical replacements.', $result->blockedReason);
    }

    public function test_it_allows_extension_within_the_limit(): void
    {
        $result = $this->makePolicy()->evaluate($this->makeContext(ReplacementResolution::EXTEND, 1));

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_requires_business_override_once_the_extension_limit_is_reached(): void
    {
        $result = $this->makePolicy()->evaluate($this->makeContext(ReplacementResolution::EXTEND, 3));

        $this->assertFalse($result->isAllowed());
        $this->assertSame('The extension limit for this plan has been reached.', $result->blockedReason);
    }

    public function test_it_always_allows_cancellation(): void
    {
        $result = $this->makePolicy()->evaluateCancellation($this->makeCancellationContext());

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_denies_a_second_pause_within_the_same_term(): void
    {
        $result = $this->makePolicy()->evaluatePause($this->makePauseContext(1));

        $this->assertFalse($result->isAllowed());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
