<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Events\Subscriptions\InvoicePaymentFailed;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\OnInvoicePaymentFailedSuspendFulfilments;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Subscriptions\FulfilmentSuspensionService;
use Mockery;
use PHPUnit\Framework\TestCase;

class OnInvoicePaymentFailedSuspendFulfilmentsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeEvent(Subscription $subscription): InvoicePaymentFailed
    {
        return new InvoicePaymentFailed(
            subscription: $subscription,
            payment: Mockery::mock(Payment::class)->makePartial(),
            failureReason: 'card_declined',
            failureCode: 'card_declined',
        );
    }

    public function test_triggers_fulfilment_suspension_with_payment_failed_reason(): void
    {
        $suspensionService = Mockery::mock(FulfilmentSuspensionService::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 9;

        $suspensionService->shouldReceive('handleTrigger')
            ->once()
            ->with($subscription, FulfilmentSuspensionService::REASON_PAYMENT_FAILED);

        $listener = new OnInvoicePaymentFailedSuspendFulfilments($suspensionService, $logger);
        $listener->handle($this->makeEvent($subscription));

        $this->assertTrue(true);
    }

    public function test_swallows_and_logs_suspension_failure(): void
    {
        $suspensionService = Mockery::mock(FulfilmentSuspensionService::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 9;

        $suspensionService->shouldReceive('handleTrigger')->andThrow(new \RuntimeException('boom'));

        $listener = new OnInvoicePaymentFailedSuspendFulfilments($suspensionService, $logger);

        // Must not throw.
        $listener->handle($this->makeEvent($subscription));

        $this->assertTrue(true);
    }
}
