<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Models\ConsentType;
use App\Requests\Members\StoreConsentTypeRequest;
use App\Requests\Members\UpdateConsentTypeRequest;
use App\Services\Members\ConsentTypeAdminService;

class ConsentTypeAdminApiController extends Controller
{
    public function __construct(
        private readonly ConsentTypeAdminService $service,
    )
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->list((int)$request->get('page', 1));

        return $this->resourceResponse([
            'data' => $result['data']->map(fn(ConsentType $type) => $this->format($type))->toArray(),
            'meta' => $result['pagination'],
        ]);
    }

    private function format(ConsentType $consentType): array
    {
        return [
            'id' => $consentType->id,
            'code' => $consentType->code,
            'name' => $consentType->name,
            'description' => $consentType->description,
            'category' => $consentType->category,
            'required' => $consentType->required,
            'retention_days' => $consentType->retention_days,
            'data_purposes' => $consentType->data_purposes,
            'is_active' => $consentType->is_active,
        ];
    }

    public function show(int $id): JsonResponse
    {
        try {
            return $this->resourceResponse($this->format($this->service->find($id)));
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }

    public function store(StoreConsentTypeRequest $request): JsonResponse
    {
        try {
            return $this->resourceResponse($this->format($this->service->create($request->validated())), 201);
        } catch (ValidationException $validationException) {
            return $this->errorResponse($validationException->getMessage(), 422, $validationException->getErrors());
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }
    }

    public function update(int $id, UpdateConsentTypeRequest $request): JsonResponse
    {
        try {
            return $this->resourceResponse($this->format($this->service->update($id, $request->validated())));
        } catch (\InvalidArgumentException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'not found') ? 404 : 422;
            return $this->errorResponse($exception->getMessage(), $status);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->delete($id);
            return $this->jsonResponse(['message' => 'Consent type deleted.']);
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }
}
