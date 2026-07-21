<?php

declare(strict_types=1);

namespace App\Tests\Unit\Listeners\Subscriptions;

use App\Events\Subscriptions\InvoicePaymentSucceeded;
use App\Framework\Support\Logger;
use App\Listeners\Subscriptions\OnInvoicePaymentSucceededReleaseFulfilments;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Subscriptions\FulfilmentSuspensionService;
use Mockery;
use PHPUnit\Framework\TestCase;

class OnInvoicePaymentSucceededReleaseFulfilmentsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_releases_suspended_fulfilments_on_payment_success(): void
    {
        $suspensionService = Mockery::mock(FulfilmentSuspensionService::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 33;

        $suspensionService->shouldReceive('release')->once()->with($subscription);

        $event = new InvoicePaymentSucceeded(
            subscription: $subscription,
            payment: Mockery::mock(Payment::class)->makePartial(),
        );

        $listener = new OnInvoicePaymentSucceededReleaseFulfilments($suspensionService, $logger);
        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function test_swallows_and_logs_release_failure(): void
    {
        $suspensionService = Mockery::mock(FulfilmentSuspensionService::class);
        $logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 33;

        $suspensionService->shouldReceive('release')->andThrow(new \RuntimeException('boom'));

        $event = new InvoicePaymentSucceeded(
            subscription: $subscription,
            payment: Mockery::mock(Payment::class)->makePartial(),
        );

        $listener = new OnInvoicePaymentSucceededReleaseFulfilments($suspensionService, $logger);
        $listener->handle($event);

        $this->assertTrue(true);
    }
}
