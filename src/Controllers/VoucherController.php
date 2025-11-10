<?php

namespace App\Controllers;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Repositories\VoucherRepository;
use App\Requests\BulkDeleteRequest;
use App\Requests\BulkUpdateVoucherStatus;
use App\Requests\CreateVoucherRequest;
use App\Requests\UpdateVoucherRequest;
use App\Resources\VoucherResource;
use App\Search\SearchCriteriaParser;
use App\Services\VoucherService;
use Exception;

class VoucherController extends Controller
{
    private VoucherService $voucherService;
    private VoucherRepository $voucherRepository;

    public function __construct(VoucherService $voucherService, VoucherRepository $voucherRepository)
    {
        $this->voucherService = $voucherService;
        $this->voucherRepository = $voucherRepository;
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $result = $this->voucherRepository->search($criteria);

            $collection = new PaginatedResourceCollection($result, VoucherResource::class);

            return $this->resourceResponse($collection->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreateVoucherRequest $request, string $siteName): JsonResponse
    {
        try {
            $validated = $request->validated();

            $voucher = $this->voucherService->create($validated);

            return $this->jsonResponse(['voucher' => $voucher->toArray()], 201);

        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(int $id, string $siteName): JsonResponse
    {
        try {
            $voucher = $this->voucherRepository->find($id);

            if (!$voucher) {
                return $this->errorResponse('Voucher not found', 404);
            }

            return $this->jsonResponse(['voucher' => $voucher->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, UpdateVoucherRequest $request, string $siteName): JsonResponse
    {
        try {
            $validated = $request->validated();

            $voucher = $this->voucherService->update($id, $validated);

            if (!$voucher) {
                return $this->errorResponse('Voucher not found', 404);
            }

            return $this->jsonResponse(['voucher' => $voucher->toArray()]);

        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $id, string $siteName): JsonResponse
    {
        try {
            $result = $this->voucherService->delete($id);

            if (!$result) {
                return $this->errorResponse('Voucher not found', 404);
            }

            return $this->jsonResponse(['message' => 'Voucher deleted successfully']);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function checkDelete(int $id, string $siteName): JsonResponse
    {
        try {
            $result = $this->voucherService->checkDeletable($id);
            return $this->jsonResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function alternatives(int $id, string $siteName): JsonResponse
    {
        try {
            $alternatives = $this->voucherService->getAlternativeVouchers($id);
            return $this->jsonResponse(['vouchers' => $alternatives->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function duplicate(int $id, string $siteName, Request $request): JsonResponse
    {
        try {
            $newCode = $request->get('code', null);
            $newVoucher = $this->voucherService->duplicateVoucher($id, $newCode);

            if (!$newVoucher) {
                return $this->errorResponse('Voucher not found', 404);
            }

            return $this->jsonResponse(['voucher' => $newVoucher->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function validate(Request $request, string $siteName): JsonResponse
    {
        try {
            $code = $request->get('code');
            $orderValue = (float)$request->get('order_value', 0);
            $userId = $request->get('user_id', null);
            $productId = $request->get('product_id', null);

            if (!$code) {
                return $this->errorResponse('Voucher code is required', 422);
            }

            $result = $this->voucherService->validateVoucher($code, $orderValue, $userId, $productId);

            return $this->jsonResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function apply(int $id, Request $request): JsonResponse
    {
        try {
            $result = $this->voucherService->applyVoucher(
                $id,
                $request->input('user_id') ?? null,
                $request->input('discount_amount') ?? null,
                $request->input('order_id') ?? null
            );

            if (!$result) {
                return $this->errorResponse('Failed to apply voucher', 500);
            }

            return $this->jsonResponse(['message' => 'Voucher applied successfully']);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function active(string $siteName): JsonResponse
    {
        try {
            $vouchers = $this->voucherRepository->getActiveVouchers();
            return $this->jsonResponse(['vouchers' => $vouchers->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function bulkUpdateStatus(BulkUpdateVoucherStatus $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $result = $this->voucherService->bulkUpdateStatus($data['ids'], $data['status']);

            return $this->resourceResponse([
                'message' => "Bulk status update completed. Updated: " . count($result['updated']) . ", Failed: " . count($result['failed']),
                'result' => $result
            ], 200);
        } catch (ValidationException $e) {
            return $this->resourceResponse(['error' => 'Validation failed', 'errors' => $e->getErrors()], 422);
        } catch (\Exception $e) {
            return $this->resourceResponse(['error' => 'Bulk update failed: ' . $e->getMessage()], 500);
        }
    }

    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $result = $this->voucherService->bulkDelete($data['ids']);

            return $this->resourceResponse([
                'message' => "Bulk delete completed. Deleted: " . count($result['deleted']) . ", Failed: " . count($result['failed']),
                'result' => $result
            ], 200);
        } catch (ValidationException $e) {
            return $this->resourceResponse(['error' => 'Validation failed', 'errors' => $e->getErrors()], 422);
        } catch (\Exception $e) {
            return $this->resourceResponse(['error' => 'Bulk delete failed: ' . $e->getMessage()], 500);
        }
    }

    public function redemptions(int $id, string $siteName): JsonResponse
    {
        try {
            $redemptions = $this->voucherRepository->getRedemptionsByVoucher($id);
            return $this->jsonResponse(['redemptions' => $redemptions->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}