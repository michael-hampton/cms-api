<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\DTO\Subscriptions\PrintOrder\PrintOrderResult;
use App\Events\Subscriptions\PrintOrderGenerated;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\PrintOrderGeneratedListener;
use App\Models\IssueDelivery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class PrintOrderGeneratedListenerTest extends TestCase
{
    private Logger&MockInterface $logger;
    private PrintOrderGeneratedListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = Mockery::mock(Logger::class);
        $this->listener = new PrintOrderGeneratedListener($this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_logs_the_print_order_generation(): void
    {
        $issueDelivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $issueDelivery->id = 55;

        $result = new PrintOrderResult(
            issueDeliveryId: 55,
            records: [],
        );

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with(
                'PrintOrderGeneratedListener: print order generated',
                Mockery::on(fn (array $context) => $context['issue_delivery_id'] === 55
                    && $context['print_order_issue_delivery_id'] === 55
                    && $context['total_subscriber_copies'] === 0
                    && $context['record_count'] === 0),
            );

        $this->listener->handle(new PrintOrderGenerated($issueDelivery, $result));

        // Mockery expectation verification (shouldReceive()->once()) isn't
        // counted as a PHPUnit assertion unless registered explicitly — this
        // class doesn't extend the codebase's UnitTestCase (whose
        // assertPostConditions() does that registration), so add an explicit
        // assertion to avoid a false "risky: no assertions" flag, matching
        // AssignInitialSubscriptionSegmentTest's existing pattern.
        $this->assertTrue(true);
    }
}
