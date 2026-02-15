<?php

namespace App\Tests\Unit\Services\Billing\Refunds;

use App\Events\Refunds\RefundCreated;
use App\Framework\Mail\MailManager;
use App\Framework\Mail\PendingMail;
use App\Framework\Support\Logger;
use App\Listeners\Refunds\SendRefundNotification;
use App\Mail\RefundConfirmation;
use App\Models\Member;
use App\Models\Order;
use App\Models\Refund;
use App\Repositories\Billing\OrderRepository;
use Exception;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class SendRefundNotificationTest extends TestCase
{
    private MailManager $mailManager;
    private OrderRepository $orderRepository;
    private SendRefundNotification $listener;
    private Logger $logger;

    public function testSendsEmailSuccessfully(): void
    {
        $refund = m::mock(Refund::class)->makePartial();
        $refund->id = 1;
        $refund->order_id = 100;

        $order = m::mock(Order::class)->makePartial();
        $order->id = 100;
        $order->user = m::mock(Member::class)->makePartial();
        $order->user->email = 'customer@example.com';

        $event = new RefundCreated($refund, $order, 'customer_request');

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(100)
            ->andReturn($order);

        $pendingMail = m::mock(PendingMail::class);

        $this->mailManager
            ->shouldReceive('to')
            ->once()
            ->with('customer@example.com')
            ->andReturn($pendingMail);

        $pendingMail
            ->shouldReceive('send')
            ->once()
            ->with(m::type(RefundConfirmation::class));

        $this->listener->handle($event);
        $this->assertTrue(true);
    }

    public function testLogsWarningWhenOrderNotFound(): void
    {
        $refund = m::mock(Refund::class)->makePartial();
        $refund->id = 1;
        $refund->order_id = 999;

        $order = m::mock(Order::class)->makePartial();
        $order->id = 100;

        $event = new RefundCreated($refund, $order, 'customer_request');

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        // Mock static Logger
        $this->logger->shouldReceive('warning')
            ->once()
            ->with(
                'Cannot send refund notification: order not found',
                [
                    'refund_id' => 1,
                    'order_id' => 999
                ]
            );

        $this->listener->handle($event);
        $this->assertTrue(true);
    }

    public function testLogsInfoWhenOrderHasNoUser(): void
    {
        $refund = m::mock(Refund::class)->makePartial();
        $refund->id = 1;
        $refund->order_id = 100;

        $order = m::mock(Order::class)->makePartial();
        $order->id = 100;
        $order->user = null;

        $event = new RefundCreated($refund, $order, 'customer_request');

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(100)
            ->andReturn($order);

        $this->logger->shouldReceive('info')
            ->once()
            ->with(
                'Refund notification skipped: no customer email',
                [
                    'refund_id' => 1,
                    'order_id' => 100
                ]
            );

        $this->listener->handle($event);
        $this->assertTrue(true);
    }

    public function testLogsInfoWhenUserHasNoEmail(): void
    {
        $refund = m::mock(Refund::class)->makePartial();
        $refund->id = 1;
        $refund->order_id = 100;

        $order = m::mock(Order::class)->makePartial();
        $order->id = 100;
        $order->user = m::mock(Member::class)->makePartial();
        $order->user->email = null;

        $event = new RefundCreated($refund, $order, 'customer_request');

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(100)
            ->andReturn($order);

        $this->logger->shouldReceive('info')
            ->once()
            ->with(
                'Refund notification skipped: no customer email',
                [
                    'refund_id' => 1,
                    'order_id' => 100
                ]
            );

        $this->listener->handle($event);
        $this->assertTrue(true);
    }

    public function testLogsInfoWhenUserHasEmptyEmail(): void
    {
        $refund = m::mock(Refund::class)->makePartial();
        $refund->id = 1;
        $refund->order_id = 100;

        $order = m::mock(Order::class)->makePartial();
        $order->id = 100;
        $order->user = m::mock(Member::class)->makePartial();
        $order->user->email = '';

        $event = new RefundCreated($refund, $order, 'customer_request');

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(100)
            ->andReturn($order);

        $this->logger->shouldReceive('info')
            ->once()
            ->with(
                'Refund notification skipped: no customer email',
                [
                    'refund_id' => 1,
                    'order_id' => 100
                ]
            );

        $this->listener->handle($event);
        $this->assertTrue(true);
    }

    public function testLogsErrorWhenEmailSendingFails(): void
    {
        $refund = m::mock(Refund::class)->makePartial();
        $refund->id = 1;
        $refund->order_id = 100;

        $order = m::mock(Order::class)->makePartial();
        $order->id = 100;
        $order->user = m::mock(Member::class)->makePartial();
        $order->user->email = 'customer@example.com';

        $event = new RefundCreated($refund, $order, 'customer_request');

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(100)
            ->andReturn($order);

        $pendingMail = m::mock(PendingMail::class);

        $this->mailManager
            ->shouldReceive('to')
            ->once()
            ->with('customer@example.com')
            ->andReturn($pendingMail);

        $exception = new Exception('SMTP connection failed');

        $pendingMail
            ->shouldReceive('send')
            ->once()
            ->andThrow($exception);

        $this->logger->shouldReceive('error')
            ->once()
            ->with(
                'Failed to send refund notification',
                m::on(function ($context) use ($exception) {
                    return $context['refund_id'] === 1
                        && $context['error'] === 'SMTP connection failed'
                        && isset($context['trace']);
                })
            );

        $this->listener->handle($event);
        $this->assertTrue(true);
    }

    public function testHandlesDifferentRefundReasons(): void
    {
        $reasons = ['customer_request', 'damaged_item', 'out_of_stock', 'wrong_item'];

        foreach ($reasons as $reason) {
            $refund = m::mock(Refund::class)->makePartial();
            $refund->id = 1;
            $refund->order_id = 100;

            $order = m::mock(Order::class)->makePartial();
            $order->id = 100;
            $order->user = m::mock(Member::class)->makePartial();
            $order->user->email = 'customer@example.com';

            $event = new RefundCreated($refund, $order, $reason);

            $this->orderRepository
                ->shouldReceive('find')
                ->once()
                ->with(100)
                ->andReturn($order);

            $pendingMail = m::mock(PendingMail::class);

            $this->mailManager
                ->shouldReceive('to')
                ->once()
                ->with('customer@example.com')
                ->andReturn($pendingMail);

            $pendingMail
                ->shouldReceive('send')
                ->once()
                ->with(m::type(RefundConfirmation::class));

            $this->listener->handle($event);
            $this->assertTrue(true);
        }
    }

    public function testPassesCorrectRefundAndOrderToMailable(): void
    {
        $refund = m::mock(Refund::class)->makePartial();
        $refund->id = 1;
        $refund->order_id = 100;

        $order = m::mock(Order::class)->makePartial();
        $order->id = 100;
        $order->user = m::mock(Member::class)->makePartial();
        $order->user->email = 'customer@example.com';

        $event = new RefundCreated($refund, $order, 'customer_request');

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(100)
            ->andReturn($order);

        $pendingMail = m::mock(PendingMail::class);

        $this->mailManager
            ->shouldReceive('to')
            ->once()
            ->with('customer@example.com')
            ->andReturn($pendingMail);

        $pendingMail
            ->shouldReceive('send')
            ->once()
            ->with(m::on(function ($mailable) use ($refund, $order) {
                return $mailable instanceof RefundConfirmation;
            }));

        $this->listener->handle($event);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailManager = m::mock(MailManager::class);
        $this->orderRepository = m::mock(OrderRepository::class);
        $this->logger = m::mock(Logger::class);

        $this->listener = new SendRefundNotification(
            $this->mailManager,
            $this->orderRepository,
            $this->logger
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}