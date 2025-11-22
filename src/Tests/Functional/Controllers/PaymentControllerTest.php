<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PaymentControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsPaymentsList(): void
    {
        $order = $this->createOrder();
        $this->createPayment(['order_id' => $order->id]);
        $this->createPayment(['order_id' => $order->id]);

        $response = $this->getForSite('/api/payments');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('payments', $data['data']);
        $this->assertCount(2, $data['data']['payments']);
    }

    protected function createPayment(array $overrides = []): Payment
    {
        $order = isset($overrides['order_id']) ?
            Order::find($overrides['order_id']) :
            $this->createOrder();

        return Payment::create(array_merge([
            'order_id' => $order->id,
            'site_id' => $this->siteId,
            'payment_method' => 'stripe',
            'payment_provider' => 'stripe',
            'status' => 'pending',
            'amount' => 100.00,
            'currency' => 'GBP',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    public function testIndexFiltersByStatus(): void
    {
        $order = $this->createOrder();
        $this->createPayment(['order_id' => $order->id, 'status' => 'completed']);
        $this->createPayment(['order_id' => $order->id, 'status' => 'pending']);
        $this->createPayment(['order_id' => $order->id, 'status' => 'completed']);

        $response = $this->getForSite('/api/payments?status=completed');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['data']['payments']);
        foreach ($data['data']['payments'] as $payment) {
            $this->assertEquals('completed', $payment['status']);
        }
    }

    public function testShowReturnsPaymentById(): void
    {
        $order = $this->createOrder();
        $payment = $this->createPayment([
            'order_id' => $order->id,
            'transaction_id' => 'txn_12345'
        ]);

        $response = $this->getForSite("/api/payments/{$payment->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals($payment->id, $data['data']['payment']['id']);
        $this->assertEquals('txn_12345', $data['data']['payment']['transaction_id']);
    }

    public function testShowReturns404ForNonExistent(): void
    {
        $response = $this->getForSite('/api/payments/99999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testProcessPaymentSuccessfully(): void
    {
        $order = $this->createOrder();
        $payment = $this->createPayment([
            'order_id' => $order->id,
            'status' => 'pending'
        ]);

        $response = $this->postForSite("/api/payments/{$payment->id}/process", [
            'transaction_id' => 'txn_new_123'
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('processing', $data['data']['payment']['status']);
    }

    public function testCompletePaymentSuccessfully(): void
    {
        $order = $this->createOrder(['payment_status' => 'unpaid']);
        $payment = $this->createPayment([
            'order_id' => $order->id,
            'status' => 'processing'
        ]);

        $response = $this->postForSite("/api/payments/{$payment->id}/complete", [
            'transaction_id' => 'txn_complete_123'
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('completed', $data['data']['payment']['status']);
        $this->assertNotNull($data['data']['payment']['paid_at']);

        // Verify order payment status was updated
        $updatedOrder = Order::find($order->id);
        $this->assertEquals('paid', $updatedOrder->payment_status);
    }

    public function testFailPaymentSuccessfully(): void
    {
        $order = $this->createOrder();
        $payment = $this->createPayment([
            'order_id' => $order->id,
            'status' => 'processing'
        ]);

        $response = $this->postForSite("/api/payments/{$payment->id}/fail", [
            'error_message' => 'Card declined'
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('failed', $data['data']['payment']['status']);
        $this->assertEquals('Card declined', $data['data']['payment']['error_message']);
        $this->assertNotNull($data['data']['payment']['failed_at']);
    }

    public function testFailPaymentRequiresErrorMessage(): void
    {
        $order = $this->createOrder();
        $payment = $this->createPayment(['order_id' => $order->id]);

        $response = $this->postForSite("/api/payments/{$payment->id}/fail", []);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCancelPaymentSuccessfully(): void
    {
        $order = $this->createOrder();
        $payment = $this->createPayment([
            'order_id' => $order->id,
            'status' => 'pending'
        ]);

        $response = $this->postForSite("/api/payments/{$payment->id}/cancel", [
            'reason' => 'Customer requested cancellation'
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('cancelled', $data['data']['payment']['status']);
    }

    public function testCannotCancelCompletedPayment(): void
    {
        $order = $this->createOrder();
        $payment = $this->createPayment([
            'order_id' => $order->id,
            'status' => 'completed'
        ]);

        $response = $this->postForSite("/api/payments/{$payment->id}/cancel");

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRetryPaymentSuccessfully(): void
    {
        $order = $this->createOrder();
        $payment = $this->createPayment([
            'order_id' => $order->id,
            'status' => 'failed',
            'error_message' => 'Previous error'
        ]);

        $response = $this->postForSite("/api/payments/{$payment->id}/retry");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('pending', $data['data']['payment']['status']);
        $this->assertNull($data['data']['payment']['error_message']);
    }

    public function testRefundPaymentSuccessfully(): void
    {
        $order = $this->createOrder();
        $payment = $this->createPayment([
            'order_id' => $order->id,
            'status' => 'completed',
            'amount' => 100.00
        ]);

        $response = $this->postForSite("/api/payments/{$payment->id}/refund", [
            'amount' => 50.00,
            'reason' => 'Partial refund requested'
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('refunded', $data['data']['payment']['status']);
    }

    public function testRefundPaymentValidatesAmount(): void
    {
        $order = $this->createOrder();
        $payment = $this->createPayment([
            'order_id' => $order->id,
            'status' => 'completed',
            'amount' => 100.00
        ]);

        $response = $this->postForSite("/api/payments/{$payment->id}/refund", [
            'amount' => 150.00,
            'reason' => 'Test'
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testGetPaymentByTransaction(): void
    {
        $order = $this->createOrder();
        $payment = $this->createPayment([
            'order_id' => $order->id,
            'transaction_id' => 'txn_unique_123'
        ]);

        $response = $this->getForSite('/api/payments/by-transaction?transaction_id=txn_unique_123');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals($payment->id, $data['data']['payment']['id']);
    }

    public function testGetPaymentByTransactionRequiresTransactionId(): void
    {
        $response = $this->getForSite('/api/payments/by-transaction');

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testGetTotalCollected(): void
    {
        $order = $this->createOrder();

        $this->createPayment([
            'order_id' => $order->id,
            'status' => 'completed',
            'amount' => 100.00,
            'paid_at' => '2024-01-15 10:00:00'
        ]);

        $this->createPayment([
            'order_id' => $order->id,
            'status' => 'completed',
            'amount' => 200.00,
            'paid_at' => '2024-02-20 10:00:00'
        ]);

        $this->createPayment([
            'order_id' => $order->id,
            'status' => 'pending',
            'amount' => 300.00
        ]);

        $response = $this->getForSite('/api/payments/total-collected?start_date=2024-01-01&end_date=2024-12-31');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals(300.00, $data['data']['total_collected']);
    }

    public function testOrderPaymentsEndpoint(): void
    {
        $order = $this->createOrder();
        $this->createPayment(['order_id' => $order->id, 'status' => 'completed']);
        $this->createPayment(['order_id' => $order->id, 'status' => 'failed']);

        $response = $this->getForSite("/api/orders/{$order->id}/payments");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']['payments']);
    }

    public function testCreatePaymentForOrder(): void
    {
        $order = $this->createOrder(['total' => 100.00]);
        $paymentMethod = $this->createPaymentMethod(['code' => 'stripe']);

        $paymentData = [
            'payment_method' => 'stripe',
            'amount' => 100.00,
            'currency' => 'GBP'
        ];

        $response = $this->postForSite("/api/orders/{$order->id}/payments", $paymentData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('completed', $data['data']['payment']['status']);
        $this->assertEquals(100.00, $data['data']['payment']['amount']);
    }

    protected function createPaymentMethod(array $overrides = []): PaymentMethod
    {
        return PaymentMethod::create(array_merge([
            'site_id' => $this->siteId,
            'name' => 'Test Payment Method',
            'code' => 'test_' . uniqid(),
            'provider' => 'test',
            'is_active' => true,
            'requires_processing' => false,
            'sort_order' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    public function testCreatePaymentValidatesPaymentMethod(): void
    {
        $order = $this->createOrder();

        $response = $this->postForSite("/api/orders/{$order->id}/payments", [
            'payment_method' => 'invalid_method'
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }
}