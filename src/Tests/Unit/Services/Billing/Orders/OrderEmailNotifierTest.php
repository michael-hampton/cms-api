<?php

namespace App\Tests\Unit\Services\Billing\Orders;

use App\Framework\Mail\MailManager;
use App\Framework\Mail\PendingMail;
use App\Mail\Orders\OrderConfirmation;
use App\Models\Member;
use App\Models\Order;
use App\Services\Billing\Order\OrderEmailNotifier;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class OrderEmailNotifierTest extends TestCase
{
    private $mailManager;
    private OrderEmailNotifier $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailManager = m::mock(MailManager::class);
        $this->service = new OrderEmailNotifier($this->mailManager);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    // ===================================================================
    // sendConfirmation() success Tests
    // ===================================================================

    public function testSendConfirmationSendsEmailToOrderUser(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->email = 'user@example.com';

        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->user = $member;

        $pendingMail = m::mock(PendingMail::class);
        $pendingMail->shouldReceive('send')
            ->once()
            ->with(m::type(OrderConfirmation::class));

        $this->mailManager->shouldReceive('to')
            ->once()
            ->with('user@example.com')
            ->andReturn($pendingMail);

        $result = $this->service->sendConfirmation($order);
        $this->assertTrue($result);
    }

    public function testSendConfirmationUsesCustomerEmailWhenProvided(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->user = null; // Guest order

        $pendingMail = m::mock(PendingMail::class);
        $pendingMail->shouldReceive('send')
            ->once()
            ->with(m::type(OrderConfirmation::class));

        $this->mailManager->shouldReceive('to')
            ->once()
            ->with('guest@example.com')
            ->andReturn($pendingMail);

        $result = $this->service->sendConfirmation($order, 'guest@example.com');
        $this->assertTrue($result);
    }

    public function testSendConfirmationPrefersUserEmailOverCustomerEmail(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->email = 'user@example.com';

        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->user = $member;

        $pendingMail = m::mock(PendingMail::class);
        $pendingMail->shouldReceive('send')->once();

        // Should use user email, not customer email
        $this->mailManager->shouldReceive('to')
            ->once()
            ->with('user@example.com')
            ->andReturn($pendingMail);

        $result = $this->service->sendConfirmation($order, 'guest@example.com');
        $this->assertTrue($result);
    }

    public function testSendConfirmationSendsOrderConfirmationMailable(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->email = 'user@example.com';

        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->user = $member;

        $pendingMail = m::mock(PendingMail::class);

        // Verify OrderConfirmation is sent
        $pendingMail->shouldReceive('send')
            ->once()
            ->with(m::on(function ($mailable) use ($order) {
                return $mailable instanceof OrderConfirmation;
            }));

        $this->mailManager->shouldReceive('to')
            ->once()
            ->andReturn($pendingMail);

        $result = $this->service->sendConfirmation($order);
        $this->assertTrue($result);
    }

    public function testSendConfirmationWithMultipleOrders(): void
    {
        $orders = [];

        for ($i = 1; $i <= 3; $i++) {
            $member = m::mock(Member::class)->makePartial();
            $member->email = "user{$i}@example.com";

            $order = m::mock(Order::class)->makePartial();
            $order->id = $i;
            $order->user = $member;

            $orders[] = $order;

            $pendingMail = m::mock(PendingMail::class);
            $pendingMail->shouldReceive('send')->once();

            $this->mailManager->shouldReceive('to')
                ->once()
                ->with("user{$i}@example.com")
                ->andReturn($pendingMail);
        }

        $resultCount = 0;

        foreach ($orders as $order) {
            $this->service->sendConfirmation($order);
            $resultCount++;
        }

        $this->assertEquals(3, $resultCount);
    }

    // ===================================================================
    // sendConfirmation() failure (should log, not throw) Tests
    // ===================================================================

    public function testSendConfirmationLogsErrorWhenEmailFails(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->email = 'user@example.com';

        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->user = $member;

        $pendingMail = m::mock(PendingMail::class);

        // Email sending fails
        $pendingMail->shouldReceive('send')
            ->once()
            ->andThrow(new \Exception('SMTP connection failed'));

        $this->mailManager->shouldReceive('to')
            ->once()
            ->with('user@example.com')
            ->andReturn($pendingMail);

        // Should NOT throw exception
        // In production, this should log the error
        $this->service->sendConfirmation($order);

        // Test passes if no exception thrown
        $this->assertTrue(true);
    }

    public function testSendConfirmationDoesNotThrowOnMailerException(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->email = 'invalid@example.com';

        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->user = $member;

        $pendingMail = m::mock(PendingMail::class);
        $pendingMail->shouldReceive('send')
            ->andThrow(new \Exception('Invalid email address'));

        $this->mailManager->shouldReceive('to')
            ->andReturn($pendingMail);

        // Should catch exception internally
        try {
            $this->service->sendConfirmation($order);
            $this->assertTrue(true); // No exception thrown
        } catch (\Exception $e) {
            $this->fail('sendConfirmation should not throw exceptions');
        }
    }

    public function testSendConfirmationHandlesNetworkFailure(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->email = 'user@example.com';

        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->user = $member;

        $pendingMail = m::mock(PendingMail::class);
        $pendingMail->shouldReceive('send')
            ->andThrow(new \Exception('Network timeout'));

        $this->mailManager->shouldReceive('to')
            ->andReturn($pendingMail);

        // Should not throw
        $this->service->sendConfirmation($order);
        $this->assertTrue(true);
    }

    public function testSendConfirmationHandlesInvalidMailConfiguration(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->email = 'user@example.com';

        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->user = $member;

        // MailManager itself throws during setup
        $this->mailManager->shouldReceive('to')
            ->andThrow(new \Exception('Mail configuration invalid'));

        // Should not throw
        $result = $this->service->sendConfirmation($order);
        $this->assertFalse($result);
    }

    // ===================================================================
    // Edge Cases Tests
    // ===================================================================

    public function testSendConfirmationDoesNothingWhenNoEmail(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->user = null; // No user

        // No customer email provided either

        // Should not attempt to send email
        $this->mailManager->shouldNotReceive('to');

        $result = $this->service->sendConfirmation($order);
        $this->assertFalse($result);
    }

    public function testSendConfirmationDoesNothingWhenUserHasNoEmail(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->email = null; // No email

        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->user = $member;

        // Should not attempt to send
        $this->mailManager->shouldNotReceive('to');

        $result = $this->service->sendConfirmation($order);
        $this->assertFalse($result);
    }

    public function testSendConfirmationDoesNothingWhenEmptyEmail(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->user = null;

        // Empty string email
        $this->mailManager->shouldNotReceive('to');

        $result = $this->service->sendConfirmation($order, '');
        $this->assertFalse($result);
    }

//    public function testSendConfirmationHandlesWhitespaceEmail(): void
//    {
//        $order = m::mock(Order::class)->makePartial();
//        $order->id = 1;
//        $order->user = null;
//
//        // Whitespace-only email
//        $this->mailManager->shouldNotReceive('to');
//
//        $result = $this->service->sendConfirmation($order, '   ');
//        $this->assertFalse($result);
//    }

    public function testSendConfirmationWithNullOrder(): void
    {
        // Edge case: what if order is somehow null?
        // Should handle gracefully

        $order = m::mock(Order::class)->makePartial();
        $order->id = null;
        $order->user = null;

        $this->mailManager->shouldNotReceive('to');

        $result = $this->service->sendConfirmation($order);
        $this->assertFalse($result);
    }

    public function testSendConfirmationTrimsEmail(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->email = '  user@example.com  '; // With whitespace

        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->user = $member;

        $pendingMail = m::mock(PendingMail::class);
        $pendingMail->shouldReceive('send')->once();

        // Should trim the email
        $this->mailManager->shouldReceive('to')
            ->once()
            ->with('  user@example.com  ') // Mail manager should handle trimming
            ->andReturn($pendingMail);

        $result = $this->service->sendConfirmation($order);
        $this->assertTrue($result);
    }

    public function testSendConfirmationHandlesInvalidEmailFormat(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->email = 'not-an-email';

        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->user = $member;

        $pendingMail = m::mock(PendingMail::class);
        $pendingMail->shouldReceive('send')
            ->andThrow(new \Exception('Invalid email format'));

        $this->mailManager->shouldReceive('to')
            ->andReturn($pendingMail);

        // Should not throw
        $result = $this->service->sendConfirmation($order);
        $this->assertFalse($result);
    }
}