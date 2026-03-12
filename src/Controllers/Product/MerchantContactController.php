<?php

namespace App\Controllers\Product;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Repositories\Product\MerchantContactRepository;
use App\Requests\Merchant\CreateMerchantContactRequest;
use App\Requests\Merchant\UpdateMerchantContactRequest;
use App\Services\Product\MerchantContactService;
use Exception;

class MerchantContactController extends Controller
{
    protected MerchantContactService $contactService;
    private MerchantContactRepository $contactRepository;

    public function __construct(
        MerchantContactService    $contactService,
        MerchantContactRepository $contactRepository
    )
    {
        $this->contactService = $contactService;
        $this->contactRepository = $contactRepository;
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $contacts = $this->contactService->getAllContacts();

            return $this->resourceResponse([
                'success' => true,
                'items' => $contacts->toArray()
            ]);
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
                'contact' => $contact->toArray()
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
        $contact = $this->contactService->getContact($id);

        if (!$contact) {
            return $this->jsonResponse([
                'message' => 'Contact not found'
            ], 404);
        }

        return $this->jsonResponse(['contact' => $contact]);
    }

    public function update(UpdateMerchantContactRequest $request, int $id, string $siteName): JsonResponse
    {
        try {
            $updated = $this->contactService->updateContact($id, $request->validated());

            if (!$updated) {
                return $this->jsonResponse([
                    'message' => 'Contact not found'
                ], 404);
            }

            return $this->jsonResponse([
                'message' => 'Contact updated successfully',
                'contact' => $updated
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
        $deleted = $this->contactService->deleteContact($id);

        if (!$deleted) {
            return $this->jsonResponse([
                'message' => 'Contact not found'
            ], 404);
        }

        return $this->jsonResponse([
            'message' => 'Contact deleted successfully'
        ], 200);
    }

    public function getByMerchant(int $merchantId, string $siteName): JsonResponse
    {
        try {
            $contacts = $this->contactRepository->getByMerchant($merchantId);

            return $this->resourceResponse([
                'success' => true,
                'items' => $contacts->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}