<?php

namespace App\Controllers;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Requests\CreateRefundRequest;
use App\Services\Members\RefundService;
use Exception;

class RefundController extends Controller
{
    public function __construct(
        private readonly RefundService $refundService
    )
    {
        parent::__construct();
    }

    public function store(CreateRefundRequest $request): JsonResponse
    {
        try {
            $refund = $this->refundService->createRefund(
                $request->validated(),
                $request->user()->id ?? null
            );

            return $this->jsonResponse([
                'message' => 'Refund created successfully',
                'refund' => $refund->toArray()
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function index(int $orderId): JsonResponse
    {
        try {
            $refunds = $this->refundService->getRefundsByOrder($orderId);

            return $this->jsonResponse([
                'refunds' => $refunds->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function cancel(int $refundId, Request $request): JsonResponse
    {
        try {
            $this->refundService->cancelRefund(
                $refundId,
                $request->user()->id ?? null
            );

            return $this->jsonResponse([
                'message' => 'Refund cancelled successfully'
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function remainingAmount(int $orderId): JsonResponse
    {
        try {
            $amount = $this->refundService->getRemainingRefundableAmount($orderId);

            return $this->jsonResponse([
                'remaining_amount' => $amount
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}