<?php

namespace App\Controllers\Vouchers;

use App\Actions\Voucher\BulkDeleteVoucher;
use App\Actions\Voucher\CloneVoucher;
use App\Controllers\Controller;
use App\Framework\Container;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Session\Session;
use App\Repositories\Vouchers\VoucherRepository;
use App\Requests\BulkDeleteRequest;
use App\Requests\BulkUpdateVoucherStatus;
use App\Requests\CreateVoucherRequest;
use App\Requests\UpdateVoucherRequest;
use App\Resources\VoucherResource;
use App\Search\SearchCriteriaParser;
use App\Services\Shopping\CartService;
use App\Services\Vouchers\VoucherService;
use Exception;

class VoucherController extends Controller
{
    public function __construct(
        private readonly VoucherService    $voucherService,
        private readonly VoucherRepository $voucherRepository,
        private readonly CartService       $cartService
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $site): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $site);
            $result = $this->voucherRepository->search($criteria);

            $collection = new PaginatedResourceCollection($result, VoucherResource::class);

            return $this->resourceResponse($collection->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreateVoucherRequest $request, string $site): JsonResponse
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

    public function show(int $id, string $site): JsonResponse
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

    public function update(int $id, UpdateVoucherRequest $request, string $site): JsonResponse
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

    public function destroy(int $id, string $site): JsonResponse
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

    public function checkDelete(int $id, string $site): JsonResponse
    {
        try {
            $result = $this->voucherService->checkDeletable($id);
            return $this->jsonResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function alternatives(int $id, string $site): JsonResponse
    {
        try {
            $alternatives = $this->voucherService->getAlternativeVouchers($id);
            return $this->jsonResponse(['vouchers' => $alternatives->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function duplicate(int $id, string $site, Request $request): JsonResponse
    {
        try {
            $newCode = $request->get('code', null);
            $handler = Container::getInstance()->make(CloneVoucher::class);
            $results = $handler->handle($id, $newCode);

            if (!$results) {
                return $this->errorResponse('Voucher not found', 404);
            }

            return $this->jsonResponse($results, 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function validate(Request $request, string $site): JsonResponse
    {
        try {
            $code = $request->get('code');
            $orderValue = (float)$request->get('order_value', 0);
            $userId = $request->get('user_id', null);
            $productId = $request->get('product_id', null);
            $planId = $request->get('plan_id', null); // ADD THIS
            $isSubscription = $request->get('is_subscription', false); // ADD THIS

            if (!$code) {
                return $this->errorResponse('Voucher code is required', 422);
            }

            // Route to subscription validation if this is for a subscription
            if ($isSubscription || $planId) {
                if (!$planId) {
                    return $this->errorResponse('Plan ID is required for subscription vouchers', 422);
                }

                // Vouchers cannot be applied to bundle purchases — the bundle price
                // is already a pre-negotiated discount. Surface this clearly rather
                // than silently ignoring the code at checkout time.
                if ($this->cartService->containsSubscriptionBundleItems()) {
                    return $this->jsonResponse([
                        'valid' => false,
                        'message' => 'Voucher codes cannot be applied to bundle purchases.',
                        'discount' => 0,
                    ]);
                }


                $result = $this->voucherService->validateVoucherForSubscription(
                    $code,
                    $planId,
                    $userId
                );

                if ($result->valid) {
                    Session::put('applied_voucher_code', [
                        'discount' => $result->discount,
                        'voucher_id' => $result->voucher->id,
                        'code' => $code,
                        'type' => 'subscription'
                    ]);

                    return $this->jsonResponse([
                        'valid' => true,
                        'message' => 'Voucher applied successfully',
                        'discount' => $result->discount,
                        'final_price' => $result->finalPrice,
                        'voucher_id' => $result->voucher->id,
                        'voucher' => $result->voucher->toArray()
                    ]);
                }

                return $this->jsonResponse([
                    'valid' => false,
                    'message' => $result->message,
                    'discount' => 0
                ]);
            }

            $result = $this->voucherService->validateVoucherForCheckout($code, $this->cartService->getItems(), $userId);

            if ($result->valid === true) {
                Session::put('applied_voucher_code', ['discount' => $result->discount, 'voucher_id' => $result->voucher->id, 'code' => $result->voucher->code]);

//                $result = $this->voucherService->applyVoucher(
//                    $result['voucher_id'],
//                    $request->input('user_id') ?? null,
//                    $request->input('discount_amount') ?? 0,
//                    $request->input('order_id') ?? null
//                );
            }

            return $this->jsonResponse($result->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function removeVoucher()
    {
        Session::forget('applied_voucher_code');

        return $this->jsonResponse(['success' => true]);
    }

    public function apply(int $id, Request $request): JsonResponse
    {
        try {
            $result = $this->voucherService->applyVoucher(
                $id,
                $request->input('user_id') ?? null,
                $request->input('discount_amount') ?? 0,
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

    public function active(string $site): JsonResponse
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

            $handler = Container::getInstance()->make(\App\Actions\Voucher\BulkUpdateVoucherStatus::class);

            $result = $handler->handle($data['ids'], $data['status']);

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

            $handler = Container::getInstance()->make(BulkDeleteVoucher::class);

            $result = $handler->handle($data['ids']);

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

    public function redemptions(int $id, string $site): JsonResponse
    {
        try {
            $redemptions = $this->voucherRepository->getRedemptionsByVoucher($id);
            return $this->jsonResponse(['redemptions' => $redemptions->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}