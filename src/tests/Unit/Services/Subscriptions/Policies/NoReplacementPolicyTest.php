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
use App\Services\Subscriptions\Policies\NoReplacementPolicy;
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
