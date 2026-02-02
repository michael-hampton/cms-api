<?php

namespace App\Controllers\Billing;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Billing\PaymentMethodRepository;
use App\Requests\CreatePaymentMethodRequest;
use App\Requests\UpdatePaymentMethodRequest;
use Exception;

class PaymentMethodController extends Controller
{
    public function __construct(
        private readonly PaymentMethodRepository $paymentMethodRepository
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $methods = $this->paymentMethodRepository->getAllOrdered();

            return $this->resourceResponse([
                'success' => true,
                'payment_methods' => $methods->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function active(Request $request, string $siteName): JsonResponse
    {
        try {
            $methods = $this->paymentMethodRepository->getActive();

            return $this->resourceResponse([
                'success' => true,
                'payment_methods' => $methods->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show($id, string $siteName): JsonResponse
    {
        try {
            $method = $this->paymentMethodRepository->find($id);

            if (!$method) {
                return $this->errorResponse('Payment method not found', 404);
            }

            return $this->resourceResponse([
                'success' => true,
                'payment_method' => $method->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreatePaymentMethodRequest $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['site_id'] = SiteContext::getId();

            $method = $this->paymentMethodRepository->create($data);

            return $this->resourceResponse([
                'success' => true,
                'payment_method' => $method->toArray()
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, UpdatePaymentMethodRequest $request, string $siteName): JsonResponse
    {
        try {
            $method = $this->paymentMethodRepository->find($id);

            if (!$method) {
                return $this->errorResponse('Payment method not found', 404);
            }

            $data = $request->validated();

            $method = $this->paymentMethodRepository->update($id, $data);

            return $this->resourceResponse([
                'success' => true,
                'payment_method' => $method->toArray()
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $method = $this->paymentMethodRepository->find($id);

            if (!$method) {
                return $this->errorResponse('Payment method not found', 404);
            }

            $this->paymentMethodRepository->delete($id);

            return $this->successResponse('Payment method deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}