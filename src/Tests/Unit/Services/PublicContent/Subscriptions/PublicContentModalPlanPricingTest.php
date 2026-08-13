<?php

namespace App\Tests\Unit\Services\PublicContent\Subscriptions;

use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Support\Collection;
use App\Models\IssueDelivery;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Services\PublicContent\Subscriptions\PublicContentModalPlanPricing;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentModalPlanPricingTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_one_time_plan_uses_prefetched_issue_stock_without_requery(): void
    {
        $tier = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->is_default = true;
        $tier->shouldReceive('getEffectiveDigitalPrice')->andReturn(4.99);
        $tier->shouldReceive('getEffectivePrintPrice')->andReturn(7.99);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->shouldReceive('isOneTime')->andReturn(true);
        $plan->shouldReceive('hasDigitalOption')->andReturn(true);
        $plan->shouldReceive('hasPrintOption')->andReturn(true);
        $plan->shouldReceive('getNextIssue')->never();
        $plan->pricingTiers = new Collection([$tier]);

        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->metadata = ['digital_in_stock' => true];
        $issue->shouldReceive('isInStock')->andReturn(true);

        $price = (new PublicContentModalPlanPricing())->lowestEffectivePrice($plan, $issue);

        self::assertFalse($price['is_out_of_stock']);
        self::assertSame(4.99, $price['min']);
        self::assertSame(SubscriptionType::DIGITAL->value, $price['delivery_type']);
        self::assertTrue($price['show_from_prefix']);
    }

    public function test_marks_out_of_stock_when_no_format_available(): void
    {
        $tier = Mockery::mock(SubscriptionPlanPricing::class)->makePartial();
        $tier->is_default = true;
        $tier->shouldReceive('getEffectiveDigitalPrice')->andReturn(4.99);
        $tier->shouldReceive('getEffectivePrintPrice')->andReturn(7.99);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->shouldReceive('isOneTime')->andReturn(true);
        $plan->shouldReceive('hasDigitalOption')->andReturn(true);
        $plan->shouldReceive('hasPrintOption')->andReturn(true);
        $plan->pricingTiers = new Collection([$tier]);

        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->metadata = ['digital_in_stock' => false];
        $issue->shouldReceive('isInStock')->andReturn(false);

        $price = (new PublicContentModalPlanPricing())->lowestEffectivePrice($plan, $issue);

        self::assertTrue($price['is_out_of_stock']);
        self::assertNull($price['min']);
    }
}
