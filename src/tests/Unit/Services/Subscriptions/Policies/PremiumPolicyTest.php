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
use App\Services\Subscriptions\Policies\PremiumPolicy;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

class PremiumPolicyTest extends TestCase
{
    private function makeContext(ReplacementResolution $resolution, int $used = 0): PolicyContext
    {
        return new PolicyContext(
            Mockery::mock(Subscription::class)->makePartial(),
            Mockery::mock(SubscriptionPlan::class)->makePartial(),
            Mockery::mock(IssueDelivery::class)->makePartial(),
            $resolution,
            new ReplacementUsageStatistics($used, $used),
            7,
            10,
            0
        );
    }

    private function makeCancellationContext(): CancellationPolicyContext
    {
        return new CancellationPolicyContext(
            subscription: Mockery::mock(Subscription::class)->makePartial(),
            planId: 3,
            reason: null,
            cancellationNotes: null,
            requestedCancellationDate: new DateTimeImmutable(),
            currentStatus: SubscriptionStatus::ACTIVE,
            subscriptionAgeDays: 400,
            remainingTermDays: 10,
        );
    }

    private function makePauseContext(int $pausesUsedThisTerm = 5): PausePolicyContext
    {
        return new PausePolicyContext(
            subscription: Mockery::mock(Subscription::class)->makePartial(),
            planId: 3,
            requestedPauseDate: new DateTimeImmutable(),
            requestedResumeDate: new DateTimeImmutable('+30 days'),
            currentStatus: SubscriptionStatus::ACTIVE,
            subscriptionAgeDays: 400,
            remainingTermDays: 10,
            pausesUsedThisTerm: $pausesUsedThisTerm,
        );
    }

    private function makePolicy(): PremiumPolicy
    {
        $model = Mockery::mock(ReplacementPolicy::class)->makePartial();
        $model->id = 3;
        $model->name = 'Premium';
        $model->active = true;

        return new PremiumPolicy($model);
    }

    public function test_it_allows_replacement_regardless_of_usage(): void
    {
        $result = $this->makePolicy()->evaluate($this->makeContext(ReplacementResolution::REPLACE, 500));

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_allows_extension_regardless_of_usage(): void
    {
        $result = $this->makePolicy()->evaluate($this->makeContext(ReplacementResolution::EXTEND, 500));

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_always_allows_cancellation(): void
    {
        $result = $this->makePolicy()->evaluateCancellation($this->makeCancellationContext());

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_allows_unlimited_pauses(): void
    {
        $result = $this->makePolicy()->evaluatePause($this->makePauseContext(5));

        $this->assertTrue($result->isAllowed());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
