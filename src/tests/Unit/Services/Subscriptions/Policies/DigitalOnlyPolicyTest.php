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
use App\Services\Subscriptions\Policies\DigitalOnlyPolicy;
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
