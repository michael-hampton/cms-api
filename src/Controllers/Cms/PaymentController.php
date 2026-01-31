<?php

namespace App\Controllers\Cms;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Order;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Requests\CreateSubscriptionPaymentRequest;
use App\Requests\FailPaymentRequest;
use App\Requests\RefundPaymentRequest;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Billing\PaymentService;
use Exception;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService    $paymentService,
        private readonly PaymentRepository      $paymentRepository,
        private readonly StripePaymentProcessor $stripePaymentProcessor,
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $status = $request->query('status');

            if ($status) {
                $payments = $this->paymentRepository->getByStatus($status);
            } else {
                $payments = $this->paymentRepository->all();
            }

            return $this->jsonResponse([
                'success' => true,
                'payments' => $payments->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show($id, string $siteName): JsonResponse
    {
        try {
            $payment = $this->paymentRepository->find($id);

            if (!$payment) {
                return $this->errorResponse('Payment not found', 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'payment' => $payment->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function process(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();

            $payment = $this->paymentService->processPayment($id, $data);

            return $this->jsonResponse([
                'success' => true,
                'payment' => $payment->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function complete(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();

            $payment = $this->paymentService->completePayment($id, $data);

            return $this->jsonResponse([
                'success' => true,
                'payment' => $payment->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function fail(int $id, FailPaymentRequest $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->validated();

            $payment = $this->paymentService->failPayment(
                $id,
                $data['error_message'],
                $data['error_data'] ?? []
            );

            return $this->jsonResponse([
                'success' => true,
                'payment' => $payment->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function cancel(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $reason = $data['reason'] ?? null;

            $payment = $this->paymentService->cancelPayment($id, $reason);

            return $this->jsonResponse([
                'success' => true,
                'payment' => $payment->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function retry(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $payment = $this->paymentService->retryPayment($id);

            return $this->jsonResponse([
                'success' => true,
                'payment' => $payment->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function refund(int $id, RefundPaymentRequest $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->validated();

            $payment = $this->paymentService->refundPayment(
                $id,
                (float)$data['amount'],
                $data['reason']
            );

            return $this->jsonResponse([
                'success' => true,
                'payment' => $payment->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function byTransaction(Request $request, string $siteName): JsonResponse
    {
        try {
            $transactionId = $request->query('transaction_id');

            if (!$transactionId) {
                return $this->errorResponse('Transaction ID is required', 400);
            }

            $payment = $this->paymentService->getPaymentByTransactionId($transactionId);

            if (!$payment) {
                return $this->errorResponse('Payment not found', 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'payment' => $payment->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function totalCollected(Request $request, string $siteName): JsonResponse
    {
        try {
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            $total = $this->paymentService->getTotalCollected($startDate, $endDate);

            return $this->jsonResponse([
                'success' => true,
                'total_collected' => $total,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function subscriptionFailures()
    {
        try {
            $result = $this->paymentRepository->getFailedSubscriptionPayments();
            return $this->jsonResponse(['payments' => $result->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function subscriptionPayments(int $subscriptionId)
    {
        try {
            $subscription = Subscription::findOrFail($subscriptionId);

            return $this->jsonResponse(['payments' => $subscription->payments->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createSubscriptionPayment(int $subscriptionId, CreateSubscriptionPaymentRequest $request)
    {
        try {
            $result = $this->paymentService->createSubscriptionPayment($subscriptionId, $request->validated());
            return $this->jsonResponse(['payment' => $result], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function confirmPayment(Request $request)
    {
        $paymentIntentId = $request->input('payment_intent_id');
        $orderId = $request->input('order_id');
        $siteId = SiteContext::getId();

        if (!$paymentIntentId || !$orderId) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Missing required parameters'
            ], 400);
        }

        $result = $this->stripePaymentProcessor->handleOneTimeSubscriptionPayment(
            $paymentIntentId,
            $orderId,
            $siteId
        );

        if ($result['success']) {
            // Update order status
            Order::where('id', $orderId)->update([
                'status' => 'completed',
                'payment_status' => 'paid'
            ]);
        }

        return $this->jsonResponse($result, $result['success'] ? 200 : 400);
    }
}