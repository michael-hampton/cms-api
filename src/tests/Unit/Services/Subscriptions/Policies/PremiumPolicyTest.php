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
use App\Services\Subscriptions\Policies\PremiumPolicy;
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
