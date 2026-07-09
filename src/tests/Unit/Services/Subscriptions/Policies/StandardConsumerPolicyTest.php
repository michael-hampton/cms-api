<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Policies;

use App\DTO\Subscriptions\PolicyContext;
use App\DTO\Subscriptions\ReplacementUsageStatistics;
use App\Enums\Subscriptions\ReplacementResolution;
use App\Models\IssueDelivery;
use App\Models\ReplacementPolicy;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Subscriptions\Policies\StandardConsumerPolicy;
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
