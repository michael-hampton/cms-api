<?php

namespace App\Tests\Unit\Services\Billing\Payments;

use App\DTO\Stripe\PaymentIntentResultDto;
use App\Framework\Database\Database;
use App\Models\Payment;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Payments\OneTimeSubscriptionPaymentService;
use App\Services\Billing\Stripe\Contracts\StripePaymentIntentGatewayInterface;
use App\Tests\Unit\UnitTestCase;
use Exception;
use Mockery as m;
use Stripe\Service\CustomerService;
use Stripe\Service\PaymentMethodService;
use Stripe\StripeClient;
use stdClass;

class OneTimeSubscriptionPaymentServiceTest extends UnitTestCase
{
    private PaymentRepository $paymentRepository;
    private OrderRepository $orderRepository;
    private StripePaymentIntentGatewayInterface $paymentIntentGateway;
    private SubscriptionRepository $subscriptionRepository;
    private Database $databaseMock;
    private StripeClient $stripeClient;
    private PaymentMethodService $paymentMethodService;
    private CustomerService $customerService;
    private OneTimeSubscriptionPaymentService $service;

    protected function setUp(): void
    {

        $this->paymentRepository = m::mock(PaymentRepository::class);
        $this->orderRepository = m::mock(OrderRepository::class);
        $this->paymentIntentGateway = m::mock(StripePaymentIntentGatewayInterface::class);
        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->databaseMock = m::mock(Database::class);
        $this->stripeClient = m::mock(StripeClient::class);
        $this->paymentMethodService = m::mock(PaymentMethodService::class);
        $this->customerService = m::mock(CustomerService::class);

        $this->stripeClient->paymentMethods = $this->paymentMethodService;
        $this->stripeClient->customers = $this->customerService;

        // The transaction callback is executed synchronously against the same
        // mocked repositories, matching how the real Database wrapper behaves.
        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(fn(callable $callback) => $callback())
            ->byDefault();

        $this->service = new OneTimeSubscriptionPaymentService(
            $this->paymentRepository,
            $this->orderRepository,
            $this->paymentIntentGateway,
            $this->subscriptionRepository,
            $this->databaseMock,
            $this->stripeClient,
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function testConfirmPaymentRecordsCompletedPayment(): void
    {
        $this->paymentIntentGateway->shouldReceive('retrieve')
            ->once()
            ->with('pi_test123')
            ->andReturn(new PaymentIntentResultDto(
                success: true,
                paymentIntentId: 'pi_test123',
                status: 'succeeded',
                customerId: 'cus_test123',
                paymentMethodId: 'pm_test123',
                amountCents: 9999,
                currency: 'usd',
            ));

        $paymentMethod = new stdClass();
        $paymentMethod->customer = null;

        $this->paymentMethodService->shouldReceive('retrieve')
            ->once()
            ->with('pm_test123')
            ->andReturn($paymentMethod);

        $this->paymentMethodService->shouldReceive('attach')
            ->once()
            ->with('pm_test123', ['customer' => 'cus_test123']);

        $this->customerService->shouldReceive('update')
            ->once()
            ->with('cus_test123', [
                'invoice_settings' => [
                    'default_payment_method' => 'pm_test123',
                ],
            ]);

        $payment = m::mock(Payment::class)->makePartial();
        $payment->id = 1;

        $this->paymentRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function (array $data) {
                return $data['order_id'] === 10
                    && $data['subscription_id'] === 99
                    && $data['transaction_id'] === 'pi_test123'
                    && $data['payment_intent_id'] === 'pi_test123'
                    && $data['status'] === 'completed'
                    && $data['amount'] === 99.99
                    && $data['currency'] === 'USD'
                    && $data['metadata']['payment_method_saved'] === true;
            }))
            ->andReturn($payment);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with(10, ['status' => 'completed', 'payment_status' => 'paid']);

        // Expectation added here
        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(99, ['default_payment_method' => 'pm_test123']);

        $result = $this->service->confirmPayment('pi_test123', 10, 3, 99);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['payment_id']);
        $this->assertSame('pi_test123', $result['transaction_id']);
    }

    public function testConfirmPaymentContinuesWhenSavingPaymentMethodFails(): void
    {
        $this->paymentIntentGateway->shouldReceive('retrieve')
            ->once()
            ->andReturn(new PaymentIntentResultDto(
                success: true,
                paymentIntentId: 'pi_test123',
                status: 'succeeded',
                customerId: 'cus_test123',
                paymentMethodId: 'pm_test123',
                amountCents: 9999,
                currency: 'usd',
            ));

        $this->paymentMethodService->shouldReceive('retrieve')
            ->once()
            ->with('pm_test123')
            ->andThrow(new Exception('Payment method not found'));

        $payment = m::mock(Payment::class)->makePartial();
        $payment->id = 1;

        $this->paymentRepository->shouldReceive('create')->once()->andReturn($payment);
        $this->orderRepository->shouldReceive('update')->once();

        // Expectation added here
        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(99, ['default_payment_method' => 'pm_test123']);

        $result = $this->service->confirmPayment('pi_test123', 10, 3, 99);

        $this->assertTrue($result['success']);
    }

    public function testConfirmPaymentFailsWhenIntentNotSucceeded(): void
    {
        $this->paymentIntentGateway->shouldReceive('retrieve')
            ->once()
            ->andReturn(new PaymentIntentResultDto(
                success: true,
                paymentIntentId: 'pi_test123',
                status: 'requires_action',
            ));

        $result = $this->service->confirmPayment('pi_test123', 10, 3, 99);

        $this->assertFalse($result['success']);
        $this->assertSame('Payment not completed', $result['message']);
    }

    public function testConfirmPaymentFailsWhenGatewayRetrieveFails(): void
    {
        $this->paymentIntentGateway->shouldReceive('retrieve')
            ->once()
            ->andReturn(new PaymentIntentResultDto(
                success: false,
                errorMessage: 'Unable to retrieve payment',
                errorCode: 'resource_missing',
            ));

        $result = $this->service->confirmPayment('pi_missing', 10, 3, 99);

        $this->assertFalse($result['success']);
        $this->assertSame('Unable to retrieve payment', $result['message']);
        $this->assertSame('resource_missing', $result['error_code']);
    }
}