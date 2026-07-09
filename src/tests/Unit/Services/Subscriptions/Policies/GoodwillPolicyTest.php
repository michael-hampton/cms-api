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
use App\Services\Subscriptions\Policies\GoodwillPolicy;
use Mockery;
use PHPUnit\Framework\TestCase;

class GoodwillPolicyTest extends TestCase
{
    private function makeContext(ReplacementResolution $resolution): PolicyContext
    {
        return new PolicyContext(
            Mockery::mock(Subscription::class)->makePartial(),
            Mockery::mock(SubscriptionPlan::class)->makePartial(),
            Mockery::mock(IssueDelivery::class)->makePartial(),
            $resolution,
            new ReplacementUsageStatistics(999, 999),
            7,
            10,
            0
        );
    }

    private function makePolicy(bool $active = true): GoodwillPolicy
    {
        $model = Mockery::mock(ReplacementPolicy::class)->makePartial();
        $model->id = 6;
        $model->name = 'Goodwill Override';
        $model->active = $active;

        return new GoodwillPolicy($model);
    }

    public function test_it_always_allows_replacement_regardless_of_usage(): void
    {
        $result = $this->makePolicy()->evaluate($this->makeContext(ReplacementResolution::REPLACE));

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_always_allows_extension_regardless_of_usage(): void
    {
        $result = $this->makePolicy()->evaluate($this->makeContext(ReplacementResolution::EXTEND));

        $this->assertTrue($result->isAllowed());
    }

    public function test_it_still_fails_validation_when_the_row_is_inactive(): void
    {
        $result = $this->makePolicy(active: false)->validate($this->makeContext(ReplacementResolution::REPLACE));

        $this->assertFalse($result->valid);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
