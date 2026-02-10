<?php

namespace App\Controllers\Product;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Repositories\Product\MerchantRepository;
use App\Requests\CreateMerchantRequest;
use App\Requests\UpdateMerchantRequest;
use App\Resources\MerchantResource;
use App\Search\SearchCriteriaParser;
use App\Services\Product\MerchantService;
use Exception;

class MerchantController extends Controller
{
    protected MerchantService $merchantService;
    private MerchantRepository $merchantRepository;

    public function __construct(
        MerchantService    $merchantService,
        MerchantRepository $merchantRepository
    )
    {
        $this->merchantService = $merchantService;
        $this->merchantRepository = $merchantRepository;
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $result = $this->merchantRepository->search($criteria);
            $collection = new PaginatedResourceCollection($result, MerchantResource::class);

            return $this->resourceResponse($collection->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreateMerchantRequest $request, string $siteName): JsonResponse
    {
        try {
            $logoFile = $request->hasFile('logo') ? $request->file('logo') : null;

            $merchant = $this->merchantService->createMerchant(
                $request->validated(),
                $logoFile
            );

            return $this->jsonResponse([
                'message' => 'Merchant created successfully',
                'merchant' => $merchant->toArray()
            ], 201);
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

    public function show(int $id): JsonResponse
    {
        $merchant = $this->merchantService->getMerchant($id);

        if (!$merchant) {
            return $this->jsonResponse([
                'message' => 'Merchant not found'
            ], 404);
        }

        return $this->jsonResponse(['merchant' => $merchant]);
    }

    public function update(UpdateMerchantRequest $request, int $id, string $siteName): JsonResponse
    {
        try {
            $logoFile = $request->hasFile('logo') ? $request->file('logo') : null;

            $updated = $this->merchantService->updateMerchant(
                $id,
                $request->validated(),
                $logoFile
            );

            if (!$updated) {
                return $this->jsonResponse([
                    'message' => 'Merchant not found'
                ], 404);
            }

            $merchant = $this->merchantService->getMerchant($id);

            return $this->jsonResponse([
                'message' => 'Merchant updated successfully',
                'merchant' => $merchant
            ]);
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
        $deleted = $this->merchantService->deleteMerchant($id);

        if (!$deleted) {
            return $this->jsonResponse([
                'message' => 'Merchant not found'
            ], 404);
        }

        return $this->jsonResponse([
            'message' => 'Merchant deleted successfully'
        ], 200);
    }

    public function toggleStatus(int $id, string $siteName): JsonResponse
    {
        try {
            $merchant = $this->merchantService->toggleStatus($id);

            if (!$merchant) {
                return $this->jsonResponse([
                    'message' => 'Merchant not found'
                ], 404);
            }

            return $this->jsonResponse([
                'message' => 'Merchant status updated successfully',
                'merchant' => $merchant
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function bulkUpdateStatus(Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $ids = $data['ids'] ?? [];
            $isActive = $data['is_active'] ?? true;

            if (empty($ids)) {
                return $this->errorResponse('No merchant IDs provided', 400);
            }

            $updated = $this->merchantService->bulkUpdateStatus($ids, $isActive);

            return $this->jsonResponse([
                'message' => "Updated {$updated} merchant(s)",
                'count' => $updated
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function bulkDelete(Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $ids = $data['ids'] ?? [];

            if (empty($ids)) {
                return $this->errorResponse('No merchant IDs provided', 400);
            }

            $deleted = $this->merchantService->bulkDelete($ids);

            return $this->jsonResponse([
                'message' => "Deleted {$deleted} merchant(s)",
                'count' => $deleted
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function active(Request $request, string $siteName): JsonResponse
    {
        try {
            $merchants = $this->merchantService->getActiveMerchants();

            return $this->resourceResponse([
                'success' => true,
                'items' => $merchants->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function statistics(string $siteName, Request $request): JsonResponse
    {
        $merchantId = $request->get('merchant_id') ?? null;

        try {
            $stats = $this->merchantRepository->getStatistics($merchantId);

            return $this->resourceResponse([
                'success' => true,
                'data' => $stats
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getNotes(int $merchantId): JsonResponse
    {
        try {
            $notes = $this->merchantRepository->getNotes($merchantId);
            return $this->resourceResponse([
                'success' => true,
                'data' => $notes
            ]);

        } catch (Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 500);
        }
    }

    public function createNote(Request $request, int $merchantId): JsonResponse
    {
        try {
            $result = $this->merchantRepository->createNote($merchantId, Auth::id(), $request->input('content'));

            if (!$result) {
                return $this->errorResponse(
                    'Something went wrong',
                );
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Merchant note created successfully',
            ]);

        } catch (Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 500);
        }
    }

    public function updateNote(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->merchantRepository->updateNote($id, $request->input('content'));

            if (!$result) {
                return $this->errorResponse(
                    'Something went wrong',
                );
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Merchant note updated successfully',
            ]);

        } catch (Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 500);
        }
    }

    public function deleteNote(int $id, string $siteName): JsonResponse
    {
        try {
            $result = $this->merchantRepository->deleteNote($id);

            if (!$result) {
                return $this->errorResponse(
                    'Something went wrong',
                );
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Merchant note deleted successfully',
            ]);
        } catch (Exception $exception) {
            return $this->errorResponse($exception->getMessage(), 500);
        }
    }
}