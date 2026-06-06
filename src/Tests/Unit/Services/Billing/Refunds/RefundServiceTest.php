<?php

namespace App\Tests\Unit\Services\Billing\Refunds;

use App\DTO\Payments\StripeRefundResult;
use App\Enums\Refunds\RefundStatus;
use App\Enums\Refunds\RefundType;
use App\Events\Refunds\RefundCancelled;
use App\Events\Refunds\RefundCreated;
use App\Events\Refunds\RefundFailed;
use App\Events\Refunds\RefundProcessed;
use App\Exceptions\Orders\OrderNotFoundException;
use App\Exceptions\Orders\OrderNotRefundableException;
use App\Exceptions\Orders\RefundAlreadyProcessedException;
use App\Exceptions\Orders\RefundAmountExceedsRemainingException;
use App\Exceptions\Orders\RefundNotCancellableException;
use App\Exceptions\Orders\RefundNotFoundException;
use App\Exceptions\Payments\RefundGatewayException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\Order;
use App\Models\Refund;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Billing\RefundRepository;
use App\Services\Billing\Refund\OrderStatusUpdater;
use App\Services\Billing\Refund\RefundAmountCalculator;
use App\Services\Billing\Refund\RefundAmountValidator;
use App\Services\Billing\Refund\RefundItemRestockHandler;
use App\Services\Billing\Refund\RefundService;
use App\Services\Billing\Stripe\Contracts\StripeRefundGatewayInterface;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class RefundServiceTest extends FunctionalTestCase
{
    private RefundRepository $refundRepository;
    private OrderRepository $orderRepository;
    private RefundAmountCalculator $amountCalculator;
    private RefundAmountValidator $amountValidator;
    private RefundItemRestockHandler $restockHandler;
    private OrderStatusUpdater $orderStatusUpdater;
    private EventDispatcher $eventDispatcher;
    private StripeRefundGatewayInterface $stripeRefundGateway;
    private Database $databaseMock;
    private RefundService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->refundRepository    = m::mock(RefundRepository::class);
        $this->orderRepository     = m::mock(OrderRepository::class);
        $this->amountCalculator    = m::mock(RefundAmountCalculator::class);
        $this->amountValidator     = m::mock(RefundAmountValidator::class);
        $this->restockHandler      = m::mock(RefundItemRestockHandler::class);
        $this->orderStatusUpdater  = m::mock(OrderStatusUpdater::class);
        $this->eventDispatcher     = m::mock(EventDispatcher::class);
        $this->stripeRefundGateway = m::mock(StripeRefundGatewayInterface::class);
        $this->databaseMock        = m::mock(Database::class);

        /**
         * The refactored RefundService uses multiple smaller transactions.
         * Do not assert once() globally. Let each transaction callback execute.
         */
        $this->databaseMock
            ->shouldReceive('transaction')
            ->byDefault()
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->service = new RefundService(
            $this->refundRepository,
            $this->orderRepository,
            $this->amountCalculator,
            $this->amountValidator,
            $this->restockHandler,
            $this->orderStatusUpdater,
            $this->eventDispatcher,
            $this->stripeRefundGateway,
            $this->databaseMock,
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Stripe-backed createRefund()
    // ─────────────────────────────────────────────────────────────────────

    public function testCreateRefundCallsStripeWhenOrderHasPaymentIntentId(): void
    {
        $order                    = $this->createMockOrder(1, 200.00);
        $order->payment_intent_id = 'pi_test_123';
        $order->currency          = 'gbp';

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value, 1);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(1, ['payments'])
            ->andReturn($order);

        $order
            ->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $this->amountValidator
            ->shouldReceive('validateAmount')
            ->once()
            ->with($order, 200.00);

        $this->amountValidator
            ->shouldReceive('getRemainingAmount')
            ->once()
            ->with($order)
            ->andReturn(200.00);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->with(m::on(fn (array $data) =>
                $data['order_id'] === 1 &&
                $data['refund_amount'] === 200.00 &&
                $data['refund_type'] === RefundType::FULL->value &&
                $data['status'] === RefundStatus::PENDING->value &&
                $data['site_id'] === 1
            ))
            ->andReturn($refund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundCreated::class));

        $this->stripeRefundGateway
            ->shouldReceive('refundPaymentIntent')
            ->once()
            ->with(
                'pi_test_123',
                20000,
                'gbp',
                m::on(fn (array $meta) =>
                    $meta['order_id'] === '1' &&
                    $meta['refund_id'] === '1' &&
                    $meta['site_id'] === '1'
                ),
                'order_refund_1'
            )
            ->andReturn(new StripeRefundResult('re_abc', 'succeeded', 20000, 'gbp'));

        $this->refundRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, m::on(fn (array $data) =>
                $data['stripe_refund_id'] === 're_abc' &&
                $data['stripe_refund_status'] === 'succeeded' &&
                isset($data['stripe_refunded_at']) &&
                isset($data['updated_at'])
            ));

        $this->refundRepository
            ->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($refund);

        $refund
            ->shouldReceive('isPending')
            ->once()
            ->andReturn(true);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(1, ['payments'])
            ->andReturn($order);

        $order
            ->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $this->refundRepository
            ->shouldReceive('updateRefundStatus')
            ->once()
            ->with(1, RefundStatus::PROCESSED->value, null)
            ->andReturn(true);

        $this->orderStatusUpdater
            ->shouldReceive('updateAfterRefund')
            ->once()
            ->with($order);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $result = $this->service->createRefund([
            'order_id'        => 1,
            'refund_amount'   => 200.00,
            'reason'          => 'customer_request',
            'notify_customer' => true,
            'restock_items'   => false,
        ]);

        $this->assertSame($refund, $result);
    }

    public function testCreateRefundStoresStripeRefundIdOnSuccess(): void
    {
        $order                    = $this->createMockOrder(1, 100.00);
        $order->payment_intent_id = 'pi_xyz';
        $order->currency          = 'usd';

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value, 1);

        $this->expectInitialOrderValidation($order, 1, 100.00, 100.00);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($refund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundCreated::class));

        $this->stripeRefundGateway
            ->shouldReceive('refundPaymentIntent')
            ->once()
            ->with(
                'pi_xyz',
                10000,
                'usd',
                m::type('array'),
                'order_refund_1'
            )
            ->andReturn(new StripeRefundResult('re_stored_123', 'succeeded', 10000, 'usd'));

        $this->refundRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, m::on(fn (array $data) =>
                $data['stripe_refund_id'] === 're_stored_123' &&
                $data['stripe_refund_status'] === 'succeeded' &&
                isset($data['stripe_refunded_at'])
            ));

        $this->expectSuccessfulCompletion($order, $refund, 1);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $result = $this->service->createRefund([
            'order_id'        => 1,
            'refund_amount'   => 100.00,
            'reason'          => 'customer_request',
            'notify_customer' => true,
            'restock_items'   => false,
        ]);

        $this->assertSame($refund, $result);
    }

    public function testStripeRefundAmountIsConvertedToCents(): void
    {
        $order                    = $this->createMockOrder(1, 14.99);
        $order->payment_intent_id = 'pi_pence';
        $order->currency          = 'gbp';

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value, 1);

        $this->expectInitialOrderValidation($order, 1, 14.99, 14.99);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($refund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundCreated::class));

        $this->stripeRefundGateway
            ->shouldReceive('refundPaymentIntent')
            ->once()
            ->with(
                'pi_pence',
                1499,
                'gbp',
                m::type('array'),
                'order_refund_1'
            )
            ->andReturn(new StripeRefundResult('re_p', 'succeeded', 1499, 'gbp'));

        $this->refundRepository
            ->shouldReceive('update')
            ->once();

        $this->expectSuccessfulCompletion($order, $refund, 1);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $this->service->createRefund([
            'order_id'        => 1,
            'refund_amount'   => 14.99,
            'reason'          => 'customer_request',
            'notify_customer' => true,
            'restock_items'   => false,
        ]);

        $this->assertTrue(true);
    }

    public function testStripeMetadataContainsOrderRefundAndSiteIds(): void
    {
        $order                    = $this->createMockOrder(42, 50.00);
        $order->payment_intent_id = 'pi_meta';
        $order->currency          = 'gbp';
        $order->site_id           = 7;

        $refund = $this->createMockRefund(99, RefundStatus::PENDING->value, 42);

        $this->expectInitialOrderValidation($order, 42, 50.00, 50.00);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($refund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundCreated::class));

        $this->stripeRefundGateway
            ->shouldReceive('refundPaymentIntent')
            ->once()
            ->with(
                'pi_meta',
                5000,
                'gbp',
                ['order_id' => '42', 'refund_id' => '99', 'site_id' => '7'],
                'order_refund_99'
            )
            ->andReturn(new StripeRefundResult('re_m', 'succeeded', 5000, 'gbp'));

        $this->refundRepository
            ->shouldReceive('update')
            ->once();

        $this->expectSuccessfulCompletion($order, $refund, 99, 42);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $this->service->createRefund([
            'order_id'        => 42,
            'refund_amount'   => 50.00,
            'reason'          => 'customer_request',
            'notify_customer' => true,
            'restock_items'   => false,
        ]);

        $this->assertTrue(true);
    }

    public function testCreateRefundMarksRefundFailedWhenStripeFails(): void
    {
        $order                    = $this->createMockOrder(1, 200.00);
        $order->payment_intent_id = 'pi_test_fail';
        $order->currency          = 'gbp';

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value, 1);

        $this->expectInitialOrderValidation($order, 1, 200.00, 200.00);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($refund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundCreated::class));

        $this->stripeRefundGateway
            ->shouldReceive('refundPaymentIntent')
            ->once()
            ->with(
                'pi_test_fail',
                20000,
                'gbp',
                m::type('array'),
                'order_refund_1'
            )
            ->andThrow(new RefundGatewayException('Card declined'));

        $this->refundRepository
            ->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($refund);

        $this->refundRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, m::on(fn (array $data) =>
                $data['status'] === RefundStatus::FAILED->value &&
                $data['stripe_failure_reason'] === 'Card declined' &&
                isset($data['updated_at'])
            ));

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundFailed::class));

        $this->refundRepository
            ->shouldNotReceive('updateRefundStatus');

        $this->orderStatusUpdater
            ->shouldNotReceive('updateAfterRefund');

        $this->expectException(RefundGatewayException::class);
        $this->expectExceptionMessage('Card declined');

        $this->service->createRefund([
            'order_id'      => 1,
            'refund_amount' => 200.00,
            'reason'        => 'customer_request',
            'restock_items' => false,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Local/manual createRefund()
    // ─────────────────────────────────────────────────────────────────────

    public function testCreateRefundSkipsStripeWhenOrderHasNoPaymentIntentId(): void
    {
        $order                    = $this->createMockOrder(1, 200.00);
        $order->payment_intent_id = null;

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value, 1);

        $this->expectInitialOrderValidation($order, 1, 200.00, 200.00);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($refund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundCreated::class));

        $this->stripeRefundGateway
            ->shouldNotReceive('refundPaymentIntent');

        $this->expectSuccessfulCompletion($order, $refund, 1);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $result = $this->service->createRefund([
            'order_id'        => 1,
            'refund_amount'   => 200.00,
            'reason'          => 'customer_request',
            'notify_customer' => true,
            'restock_items'   => false,
        ]);

        $this->assertSame($refund, $result);
    }

    public function testCreateRefundCreatesFullRefund(): void
    {
        $order                    = $this->createMockOrder(1, 200.00);
        $order->payment_intent_id = null;

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value, 1);

        $this->expectInitialOrderValidation($order, 1, 200.00, 200.00);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->with(m::on(fn (array $data) =>
                $data['order_id'] === 1 &&
                $data['refund_amount'] === 200.00 &&
                $data['refund_type'] === RefundType::FULL->value &&
                $data['reason'] === 'customer_request' &&
                $data['notify_customer'] === true &&
                $data['restock_items'] === false &&
                $data['status'] === RefundStatus::PENDING->value
            ))
            ->andReturn($refund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundCreated::class));

        $this->expectSuccessfulCompletion($order, $refund, 1);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $result = $this->service->createRefund([
            'order_id'        => 1,
            'refund_amount'   => 200.00,
            'reason'          => 'customer_request',
            'notify_customer' => true,
            'restock_items'   => false,
        ]);

        $this->assertSame($refund, $result);
    }

    public function testCreateRefundWithExplicitPartialAmount(): void
    {
        $order                    = $this->createMockOrder(1, 200.00);
        $order->payment_intent_id = null;

        $pendingRefund   = $this->createMockRefund(1, RefundStatus::PENDING->value, 1);
        $processedRefund = $this->createMockRefund(1, RefundStatus::PROCESSED->value, 1);

        $this->expectInitialOrderValidation($order, 1, 150.00, 200.00);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->with(m::on(fn (array $data) =>
                $data['order_id'] === 1 &&
                $data['refund_amount'] === 150.00 &&
                $data['refund_type'] === RefundType::PARTIAL->value &&
                $data['status'] === RefundStatus::PENDING->value
            ))
            ->andReturn($pendingRefund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundCreated::class));

        $this->expectSuccessfulCompletion($order, $pendingRefund, 1, 1, $processedRefund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $result = $this->service->createRefund([
            'order_id'        => 1,
            'refund_amount'   => 150.00,
            'reason'          => 'customer_request',
            'notify_customer' => true,
            'restock_items'   => false,
        ]);

        $this->assertSame($processedRefund, $result);
    }

    public function testCreateRefundCreatesPartialRefundWithItems(): void
    {
        $order                    = $this->createMockOrder(1, 200.00);
        $order->payment_intent_id = null;

        $items = [[
            'id'              => 1,
            'product_id'      => 1,
            'product_name'    => 'Test Product',
            'quantity'        => 2,
            'refund_quantity' => 1,
            'unit_price'      => 100.00,
            'refund_amount'   => 100.00,
        ]];

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value, 1);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(1, ['payments'])
            ->andReturn($order);

        $order
            ->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $this->amountCalculator
            ->shouldReceive('calculateFromItems')
            ->once()
            ->with($items)
            ->andReturn(100.00);

        $this->amountValidator
            ->shouldReceive('validateAmount')
            ->once()
            ->with($order, 100.00);

        $this->amountValidator
            ->shouldReceive('getRemainingAmount')
            ->once()
            ->with($order)
            ->andReturn(200.00);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->with(m::on(fn (array $data) =>
                $data['order_id'] === 1 &&
                $data['refund_amount'] === 100.00 &&
                $data['refund_type'] === RefundType::PARTIAL->value &&
                $data['reason'] === 'damaged_item'
            ))
            ->andReturn($refund);

        $this->refundRepository
            ->shouldReceive('createRefundItem')
            ->once()
            ->with(m::on(fn (array $data) =>
                $data['refund_id'] === 1 &&
                $data['order_item_id'] === 1 &&
                $data['product_id'] === 1 &&
                $data['product_name'] === 'Test Product' &&
                $data['quantity'] === 2 &&
                $data['refund_quantity'] === 1 &&
                $data['unit_price'] === 100.00 &&
                $data['refund_amount'] === 100.00
            ));

        /**
         * The refactored service dispatches RefundCreated when the pending
         * refund is created. If you still want notify_customer=false to suppress
         * this event, add that condition back into RefundService.
         */
        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundCreated::class));

        $this->expectSuccessfulCompletion(
            order: $order,
            pendingRefund: $refund,
            refundId: 1,
            orderId: 1,
            returnedRefund: $refund,
            shouldRestockItems: true
        );

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $result = $this->service->createRefund([
            'order_id'        => 1,
            'reason'          => 'damaged_item',
            'items'           => $items,
            'notify_customer' => false,
            'restock_items'   => true,
        ]);

        $this->assertSame($refund, $result);
    }

    public function testCreateRefundCalculatesAmountFromItems(): void
    {
        $order                    = $this->createMockOrder(1, 200.00);
        $order->payment_intent_id = null;

        $items = [
            ['product_name' => 'Product 1', 'refund_amount' => 50.00],
            ['product_name' => 'Product 2', 'refund_amount' => 30.00],
        ];

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value, 1);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(1, ['payments'])
            ->andReturn($order);

        $order
            ->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $this->amountCalculator
            ->shouldReceive('calculateFromItems')
            ->once()
            ->with($items)
            ->andReturn(80.00);

        $this->amountValidator
            ->shouldReceive('validateAmount')
            ->once()
            ->with($order, 80.00);

        $this->amountValidator
            ->shouldReceive('getRemainingAmount')
            ->once()
            ->with($order)
            ->andReturn(200.00);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->with(m::on(fn (array $data) =>
                $data['refund_amount'] === 80.00 &&
                $data['refund_type'] === RefundType::PARTIAL->value
            ))
            ->andReturn($refund);

        $this->refundRepository
            ->shouldReceive('createRefundItem')
            ->twice();

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundCreated::class));

        $this->expectSuccessfulCompletion($order, $refund, 1);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $result = $this->service->createRefund([
            'order_id'        => 1,
            'reason'          => 'damaged_item',
            'items'           => $items,
            'notify_customer' => true,
            'restock_items'   => false,
        ]);

        $this->assertSame($refund, $result);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Validation guards
    // ─────────────────────────────────────────────────────────────────────

    public function testCreateRefundThrowsExceptionForNonExistentOrder(): void
    {
        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(999, ['payments'])
            ->andReturn(null);

        $this->expectException(OrderNotFoundException::class);
        $this->expectExceptionMessage('Order with ID 999 not found');

        $this->service->createRefund([
            'order_id'      => 999,
            'refund_amount' => 100.00,
            'reason'        => 'customer_request',
        ]);
    }

    public function testCreateRefundThrowsExceptionForUnrefundableOrder(): void
    {
        $order = $this->createMockOrder(1, 200.00);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(1, ['payments'])
            ->andReturn($order);

        $order
            ->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(false);

        $this->expectException(OrderNotRefundableException::class);
        $this->expectExceptionMessage('Order 1 cannot be refunded');

        $this->service->createRefund([
            'order_id'      => 1,
            'refund_amount' => 200.00,
            'reason'        => 'customer_request',
        ]);
    }

    public function testCreateRefundThrowsExceptionWhenAmountExceedsRemaining(): void
    {
        $order = $this->createMockOrder(1, 200.00);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(1, ['payments'])
            ->andReturn($order);

        $order
            ->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $this->amountValidator
            ->shouldReceive('validateAmount')
            ->once()
            ->with($order, 150.00)
            ->andThrow(RefundAmountExceedsRemainingException::create(150.00, 50.00));

        $this->amountValidator
            ->shouldNotReceive('getRemainingAmount');

        $this->refundRepository
            ->shouldNotReceive('create');

        $this->expectException(RefundAmountExceedsRemainingException::class);

        $this->service->createRefund([
            'order_id'      => 1,
            'refund_amount' => 150.00,
            'reason'        => 'customer_request',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // processRefund()
    // ─────────────────────────────────────────────────────────────────────

    public function testProcessRefundProcessesPendingManualRefund(): void
    {
        $refund           = $this->createMockRefund(1, RefundStatus::PENDING->value, 10);
        $refund->order_id = 10;

        $order                    = $this->createMockOrder(10, 200.00);
        $order->payment_intent_id = null;

        $this->refundRepository
            ->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($refund);

        $refund
            ->shouldReceive('isPending')
            ->once()
            ->andReturn(true);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(10, ['payments'])
            ->andReturn($order);

        $order
            ->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $this->refundRepository
            ->shouldReceive('updateRefundStatus')
            ->once()
            ->with(1, RefundStatus::PROCESSED->value, 123)
            ->andReturn(true);

        $this->orderStatusUpdater
            ->shouldReceive('updateAfterRefund')
            ->once()
            ->with($order);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $result = $this->service->processRefund(1, 123);

        $this->assertTrue($result);
    }

    public function testProcessRefundAllowsStripeBackedRefundWhenStripeRefundIdExists(): void
    {
        $refund                   = $this->createMockRefund(1, RefundStatus::PENDING->value, 10);
        $refund->order_id         = 10;
        $refund->stripe_refund_id = 're_existing';

        $order                    = $this->createMockOrder(10, 200.00);
        $order->payment_intent_id = 'pi_existing';

        $this->refundRepository
            ->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($refund);

        $refund
            ->shouldReceive('isPending')
            ->once()
            ->andReturn(true);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(10, ['payments'])
            ->andReturn($order);

        $order
            ->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $this->refundRepository
            ->shouldReceive('updateRefundStatus')
            ->once()
            ->with(1, RefundStatus::PROCESSED->value, 123)
            ->andReturn(true);

        $this->orderStatusUpdater
            ->shouldReceive('updateAfterRefund')
            ->once()
            ->with($order);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $result = $this->service->processRefund(1, 123);

        $this->assertTrue($result);
    }

    public function testProcessRefundRefusesStripeBackedRefundWithoutStripeRefundId(): void
    {
        $refund                   = $this->createMockRefund(1, RefundStatus::PENDING->value, 10);
        $refund->order_id         = 10;
        $refund->stripe_refund_id = null;

        $order                    = $this->createMockOrder(10, 200.00);
        $order->payment_intent_id = 'pi_existing';

        $this->refundRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($refund);

        $refund
            ->shouldReceive('isPending')
            ->once()
            ->andReturn(true);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(10, ['payments'])
            ->andReturn($order);

        $order
            ->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $this->refundRepository
            ->shouldNotReceive('updateRefundStatus');

        $this->orderStatusUpdater
            ->shouldNotReceive('updateAfterRefund');

        $this->expectException(RefundGatewayException::class);
        $this->expectExceptionMessage(
            'Cannot manually process a Stripe-backed refund without a Stripe refund ID.'
        );

        $this->service->processRefund(1, 123);
    }

    public function testProcessRefundThrowsExceptionForNonExistentRefund(): void
    {
        $this->refundRepository
            ->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(RefundNotFoundException::class);
        $this->expectExceptionMessage('Refund with ID 999 not found');

        $this->service->processRefund(999);
    }

    public function testProcessRefundThrowsExceptionForAlreadyProcessedRefund(): void
    {
        $refund = $this->createMockRefund(1, RefundStatus::PROCESSED->value, 10);

        $this->refundRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($refund);

        $refund
            ->shouldReceive('isPending')
            ->once()
            ->andReturn(false);

        $this->expectException(RefundAlreadyProcessedException::class);
        $this->expectExceptionMessage('Refund 1 has already been processed');

        $this->service->processRefund(1);
    }

    // ─────────────────────────────────────────────────────────────────────
    // cancelRefund()
    // ─────────────────────────────────────────────────────────────────────

    public function testCancelRefundCancelsPendingRefund(): void
    {
        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value, 10);

        $this->refundRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($refund);

        $refund
            ->shouldReceive('isPending')
            ->once()
            ->andReturn(true);

        $this->refundRepository
            ->shouldReceive('updateRefundStatus')
            ->once()
            ->with(1, RefundStatus::CANCELLED->value, 456)
            ->andReturn(true);

        $this->refundRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($refund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundCancelled::class));

        $result = $this->service->cancelRefund(1, 456);

        $this->assertTrue($result);
    }

    public function testCancelRefundThrowsExceptionForNonPendingRefund(): void
    {
        $refund = $this->createMockRefund(1, RefundStatus::PROCESSED->value, 10);

        $this->refundRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($refund);

        $refund
            ->shouldReceive('isPending')
            ->once()
            ->andReturn(false);

        $this->expectException(RefundNotCancellableException::class);
        $this->expectExceptionMessage('Only pending refunds can be cancelled');

        $this->service->cancelRefund(1);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Queries
    // ─────────────────────────────────────────────────────────────────────

    public function testGetRefundsByOrderReturnsRefunds(): void
    {
        $mockCollection = m::mock(\App\Framework\Support\Collection::class);

        $this->refundRepository
            ->shouldReceive('findByOrderId')
            ->once()
            ->with(1)
            ->andReturn($mockCollection);

        $result = $this->service->getRefundsByOrder(1);

        $this->assertSame($mockCollection, $result);
    }

    public function testGetRemainingRefundableAmountReturnsCorrectAmount(): void
    {
        $order = $this->createMockOrder(1, 200.00);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($order);

        $this->amountValidator
            ->shouldReceive('getRemainingAmount')
            ->once()
            ->with($order)
            ->andReturn(50.00);

        $this->assertEquals(50.00, $this->service->getRemainingRefundableAmount(1));
    }

    public function testGetRemainingRefundableAmountReturnsZeroForNonExistentOrder(): void
    {
        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->assertEquals(0.0, $this->service->getRemainingRefundableAmount(999));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Restocking
    // ─────────────────────────────────────────────────────────────────────

    public function testCreateRefundRestocksItemsWhenEnabled(): void
    {
        $order                    = $this->createMockOrder(1, 200.00);
        $order->payment_intent_id = null;

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value, 1);

        $this->expectInitialOrderValidation($order, 1, 200.00, 200.00);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($refund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundCreated::class));

        $this->expectSuccessfulCompletion(
            order: $order,
            pendingRefund: $refund,
            refundId: 1,
            orderId: 1,
            returnedRefund: $refund,
            shouldRestockItems: true
        );

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $this->service->createRefund([
            'order_id'      => 1,
            'refund_amount' => 200.00,
            'reason'        => 'customer_request',
            'restock_items' => true,
        ]);

        $this->assertTrue(true);
    }

    public function testCreateRefundSkipsRestockWhenDisabled(): void
    {
        $order                    = $this->createMockOrder(1, 200.00);
        $order->payment_intent_id = null;

        $refund = $this->createMockRefund(1, RefundStatus::PENDING->value, 1);

        $this->expectInitialOrderValidation($order, 1, 200.00, 200.00);

        $this->refundRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($refund);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundCreated::class));

        $this->expectSuccessfulCompletion(
            order: $order,
            pendingRefund: $refund,
            refundId: 1,
            orderId: 1,
            returnedRefund: $refund,
            shouldRestockItems: false
        );

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(m::type(RefundProcessed::class));

        $this->service->createRefund([
            'order_id'      => 1,
            'refund_amount' => 200.00,
            'reason'        => 'customer_request',
            'restock_items' => false,
        ]);

        $this->assertTrue(true);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function expectInitialOrderValidation(
        Order $order,
        int $orderId,
        float $refundAmount,
        float $remainingAmount,
    ): void {
        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with($orderId, ['payments'])
            ->andReturn($order);

        $order
            ->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        $this->amountValidator
            ->shouldReceive('validateAmount')
            ->once()
            ->with($order, $refundAmount);

        $this->amountValidator
            ->shouldReceive('getRemainingAmount')
            ->once()
            ->with($order)
            ->andReturn($remainingAmount);
    }

    private function expectSuccessfulCompletion(
        Order $order,
        Refund $pendingRefund,
        int $refundId,
        ?int $orderId = null,
        ?Refund $returnedRefund = null,
        bool $shouldRestockItems = false,
    ): void {
        $orderId ??= (int) $order->id;
        $returnedRefund ??= $pendingRefund;

        /**
         * First find inside completeLocalRefund()/completeStripeRefund().
         */
        $this->refundRepository
            ->shouldReceive('find')
            ->once()
            ->with($refundId)
            ->andReturn($pendingRefund);

        $pendingRefund
            ->shouldReceive('isPending')
            ->once()
            ->andReturn(true);

        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with($orderId, ['payments'])
            ->andReturn($order);

        $order
            ->shouldReceive('canBeRefunded')
            ->once()
            ->andReturn(true);

        if ($shouldRestockItems) {
            $this->restockHandler
                ->shouldReceive('restockItems')
                ->once()
                ->with($refundId);
        } else {
            $this->restockHandler
                ->shouldNotReceive('restockItems');
        }

        $this->refundRepository
            ->shouldReceive('updateRefundStatus')
            ->once()
            ->with($refundId, RefundStatus::PROCESSED->value, null)
            ->andReturn(true);

        $this->orderStatusUpdater
            ->shouldReceive('updateAfterRefund')
            ->once()
            ->with($order);

        /**
         * Second find inside completion flow after status update.
         */
        $this->refundRepository
            ->shouldReceive('find')
            ->once()
            ->with($refundId)
            ->andReturn($returnedRefund);
    }

    private function createMockOrder(int $id, float $total): Order
    {
        $order           = m::mock(Order::class)->makePartial();
        $order->id       = $id;
        $order->total    = $total;
        $order->site_id  = 1;
        $order->currency = 'gbp';

        return $order;
    }

    private function createMockRefund(int $id, string $status, ?int $orderId = null): Refund
    {
        $refund           = m::mock(Refund::class)->makePartial();
        $refund->id       = $id;
        $refund->status   = $status;
        $refund->order_id = $orderId ?? 1;

        return $refund;
    }
}