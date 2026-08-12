<?php

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\DTO\Subscriptions\FulfilmentDecisionContext;
use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Models\Territory;
use App\Services\Subscriptions\Printing\FulfilmentDecisionService;
use App\Services\Subscriptions\Printing\PrintAddressResolver;
use App\Services\Subscriptions\Printing\RegionResolver;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Mockery\MockInterface;

class FulfilmentDecisionServiceTest extends UnitTestCase
{
    private RegionResolver|MockInterface $regionResolver;
    private PrintAddressResolver|MockInterface $addressResolver;
    private FulfilmentDecisionService $service;

    public function test_returns_context_with_resolved_territory_and_address(): void
    {
        $subscription = $this->makeSubscription();
        $issueDelivery = $this->makeIssueDelivery();
        $territory = $this->makeTerritory(3);

        $this->addressResolver
            ->shouldReceive('resolve')
            ->once()
            ->with($subscription)
            ->andReturn($this->validResolvedAddress('CF10 3NQ'));

        $this->regionResolver
            ->shouldReceive('resolve')
            ->once()
            ->with($subscription, 'CF10 3NQ')
            ->andReturn($territory);

        $context = $this->service->decide($subscription, $issueDelivery);

        $this->assertInstanceOf(FulfilmentDecisionContext::class, $context);
        $this->assertTrue($context->hasTerritory());
        $this->assertSame(3, $context->territoryId());
        $this->assertNotEmpty($context->addressSnapshot);
    }

    private function makeSubscription(int $id = 1): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = $id;
        return $subscription;
    }

    private function makeIssueDelivery(int $id = 7): IssueDelivery
    {
        $delivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $delivery->id = $id;
        return $delivery;
    }

    private function makeTerritory(int $id): Territory
    {
        $territory = Mockery::mock(Territory::class)->makePartial();
        $territory->id = $id;
        return $territory;
    }

    private function validResolvedAddress(string $postcode): array
    {
        return [
            'full_name' => 'Jane Doe',
            'address_line_1' => '1 Test St',
            'address_line_2' => null,
            'city' => 'Cardiff',
            'postcode' => $postcode,
            'country' => 'GB',
            'snapshot' => [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'address_line_1' => '1 Test St',
                'city' => 'Cardiff',
                'postcode' => $postcode,
                'country' => 'GB',
            ],
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_returns_context_with_null_territory_when_no_territory_resolved(): void
    {
        $subscription = $this->makeSubscription();
        $issueDelivery = $this->makeIssueDelivery();

        $this->addressResolver
            ->shouldReceive('resolve')
            ->once()
            ->andReturn($this->validResolvedAddress('ZZ99 9ZZ'));

        $this->regionResolver
            ->shouldReceive('resolve')
            ->once()
            ->andReturn(null);

        $context = $this->service->decide($subscription, $issueDelivery);

        $this->assertFalse($context->hasTerritory());
        $this->assertNull($context->territoryId());
    }

    public function test_passes_postcode_from_resolved_address_to_region_resolver(): void
    {
        $subscription = $this->makeSubscription();
        $issueDelivery = $this->makeIssueDelivery();

        $this->addressResolver
            ->shouldReceive('resolve')
            ->once()
            ->andReturn($this->validResolvedAddress('EH1 1AA'));

        $this->regionResolver
            ->shouldReceive('resolve')
            ->once()
            ->with($subscription, 'EH1 1AA')
            ->andReturn(null);

        $this->service->decide($subscription, $issueDelivery);

        $this->assertTrue(true);
    }

    public function test_bubbles_exception_when_address_resolver_throws(): void
    {
        $subscription = $this->makeSubscription();
        $issueDelivery = $this->makeIssueDelivery();

        $this->addressResolver
            ->shouldReceive('resolve')
            ->once()
            ->andThrow(new \RuntimeException('no valid delivery address found'));

        $this->regionResolver->shouldNotReceive('resolve');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no valid delivery address found/');

        $this->service->decide($subscription, $issueDelivery);
    }

    public function test_channel_metadata_contains_subscription_and_issue_delivery_ids(): void
    {
        $subscription = $this->makeSubscription(id: 42);
        $issueDelivery = $this->makeIssueDelivery(id: 7);

        $this->addressResolver
            ->shouldReceive('resolve')
            ->andReturn($this->validResolvedAddress('SW1A 1AA'));

        $this->regionResolver
            ->shouldReceive('resolve')
            ->andReturn(null);

        $context = $this->service->decide($subscription, $issueDelivery);

        $this->assertSame(42, $context->channelMetadata['subscription_id']);
        $this->assertSame(7, $context->channelMetadata['issue_delivery_id']);
    }

    protected function setUp(): void
    {
        $this->regionResolver = Mockery::mock(RegionResolver::class);
        $this->addressResolver = Mockery::mock(PrintAddressResolver::class);
        $this->service = new FulfilmentDecisionService($this->regionResolver, $this->addressResolver);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}