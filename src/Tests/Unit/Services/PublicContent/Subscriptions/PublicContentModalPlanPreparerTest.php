<?php

namespace App\Tests\Unit\Services\PublicContent\Subscriptions;

use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Support\Collection;
use App\Models\SubscriptionPlan;
use App\Repositories\PublicContent\PublicContentModalIssueRepository;
use App\Services\PublicContent\Subscriptions\PublicContentModalPlanPreparer;
use App\Services\PublicContent\Subscriptions\PublicContentModalPlanPricing;
use App\Services\PublicContent\Subscriptions\PublicContentModalPlanViewModel;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentModalPlanPreparerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_prepare_wraps_plans_with_precomputed_pricing(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 9;
        $plan->slug = 'digital';

        $pricing = Mockery::mock(PublicContentModalPlanPricing::class);
        $pricing->shouldReceive('lowestEffectivePrice')
            ->once()
            ->with($plan, null)
            ->andReturn([
                'min' => 4.99,
                'tier' => null,
                'delivery_type' => SubscriptionType::DIGITAL->value,
                'available_format_count' => 1,
                'is_out_of_stock' => false,
                'show_from_prefix' => false,
            ]);
        $pricing->shouldReceive('availableDeliveryOptions')
            ->once()
            ->with($plan, null)
            ->andReturn([SubscriptionType::DIGITAL->value]);

        $issues = Mockery::mock(PublicContentModalIssueRepository::class);
        $issues->shouldReceive('nextIssuesByPlanIds')
            ->once()
            ->with([9])
            ->andReturn([]);

        $prepared = (new PublicContentModalPlanPreparer($pricing, $issues))->prepare([
            'show_modal' => true,
            'plans' => new Collection([$plan]),
            'member' => null,
        ]);

        self::assertTrue($prepared['show_modal']);
        self::assertInstanceOf(Collection::class, $prepared['plans']);
        self::assertCount(1, $prepared['plans']);
        $viewModel = $prepared['plans']->first();
        self::assertInstanceOf(PublicContentModalPlanViewModel::class, $viewModel);
        self::assertSame(4.99, $viewModel->getLowestEffectivePrice()['min']);
        self::assertSame([SubscriptionType::DIGITAL->value], $viewModel->getAvailableDeliveryOptions());
        self::assertSame('digital', $viewModel->slug);
    }
}
