<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Services\OpenCollab\EarningsService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class EarningsServiceTest extends TestCase
{
    private EarningsService $service;
    private MockInterface $paymentRepository;

    public function test_total_earnings_delegates_to_repository(): void
    {
        $this->paymentRepository
            ->shouldReceive('sumSucceededAmountForContributor')
            ->with(7)
            ->once()
            ->andReturn(1250);

        $total = $this->service->totalEarningsForContributor(7);

        $this->assertEquals(1250, $total);
    }

    public function test_total_earnings_returns_zero_when_no_payments(): void
    {
        $this->paymentRepository
            ->shouldReceive('sumSucceededAmountForContributor')
            ->andReturn(0);

        $this->assertEquals(0, $this->service->totalEarningsForContributor(7));
    }

    public function test_breakdown_delegates_to_repository(): void
    {
        $breakdown = [
            ['page_id' => 1, 'total' => 500],
            ['page_id' => 2, 'total' => 750],
        ];

        $this->paymentRepository
            ->shouldReceive('earningsBreakdownForContributor')
            ->with(7)
            ->once()
            ->andReturn($breakdown);

        $result = $this->service->earningsBreakdownForContributor(7);

        $this->assertCount(2, $result);
        $this->assertEquals(500, $result[0]['total']);
        $this->assertEquals(750, $result[1]['total']);
    }

    public function test_breakdown_returns_empty_array_when_no_pages(): void
    {
        $this->paymentRepository
            ->shouldReceive('earningsBreakdownForContributor')
            ->andReturn([]);

        $this->assertSame([], $this->service->earningsBreakdownForContributor(7));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentRepository = Mockery::mock(ArticlePaymentRepository::class);

        $this->service = new EarningsService($this->paymentRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}