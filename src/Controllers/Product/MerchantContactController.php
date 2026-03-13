<?php

namespace App\Controllers\Product;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Requests\Merchant\CreateMerchantContactRequest;
use App\Requests\Merchant\UpdateMerchantContactRequest;
use App\Resources\MerchantContactResource;
use App\Services\Product\MerchantContactService;
use Exception;

class MerchantContactController extends Controller
{
    public function __construct(
        private readonly MerchantContactService $contactService,
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $contacts = $this->contactService->getAllContacts();

            return $this->resourceResponse(MerchantContactResource::collection($contacts)->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreateMerchantContactRequest $request, string $siteName): JsonResponse
    {
        try {
            $contact = $this->contactService->createContact($request->validated());

            return $this->jsonResponse([
                'message' => 'Contact created successfully',
                'contact' => MerchantContactResource::make($contact)->toArray(),
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $contact = $this->contactService->getContact($id);

            if (!$contact) {
                return $this->errorResponse('Contact not found', 404);
            }

            return $this->resourceResponse([
                'contact' => MerchantContactResource::make($contact)->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(UpdateMerchantContactRequest $request, int $id, string $siteName): JsonResponse
    {
        try {
            $contact = $this->contactService->updateContact($id, $request->validated());

            if (!$contact) {
                return $this->errorResponse('Contact not found', 404);
            }

            return $this->resourceResponse([
                'message' => 'Contact updated successfully',
                'contact' => MerchantContactResource::make($contact)->toArray(),
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $id, string $siteName): JsonResponse
    {
        try {
            $deleted = $this->contactService->deleteContact($id);

            if (!$deleted) {
                return $this->errorResponse('Contact not found', 404);
            }

            return $this->successResponse('Contact deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getByMerchant(int $merchantId, string $siteName): JsonResponse
    {
        try {
            $contacts = $this->contactService->getContactsByMerchant($merchantId);

            return $this->resourceResponse(MerchantContactResource::collection($contacts)->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}