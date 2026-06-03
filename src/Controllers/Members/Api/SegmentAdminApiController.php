<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Enums\Member\SegmentSubjectType;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Models\Segment;
use App\Models\SegmentRule;
use App\Requests\Members\StoreSegmentRequest;
use App\Requests\Members\UpdateSegmentRequest;
use App\Services\Members\SegmentAdminService;

class SegmentAdminApiController extends Controller
{
    public function __construct(
        private readonly SegmentAdminService $service,
    )
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->list(
            page: (int)$request->get('page', 1),
            perPage: (int)$request->get('per_page', 20),
            search: $request->get('search') ?: null,
            sortBy: $request->get('sort_by', 'name'),
            sortOrder: $request->get('sort_order', 'asc'),
            subjectType: $request->get('subject_type', SegmentSubjectType::Member->value),
        );

        return $this->resourceResponse([
            'data' => $result['data']->map(fn(Segment $segment) => $this->format($segment))->toArray(),
            'meta' => $result['pagination'],
        ]);
    }

    private function format(Segment $segment): array
    {
        return [
            'id' => $segment->id,
            'key' => $segment->key,
            'name' => $segment->name,
            'description' => $segment->description,
            'category' => $segment->category,
            'is_active' => $segment->is_active,
            'rules' => $segment->rules?->map(fn(SegmentRule $rule) => [
                    'id' => $rule->id,
                    'field' => $rule->field,
                    'operator' => $rule->operator,
                    'value' => $rule->decodedValue(),
                    'boolean' => $rule->boolean,
                    'sort_order' => $rule->sort_order,
                ])->toArray() ?? [],
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

    public function store(StoreSegmentRequest $request): JsonResponse
    {
        try {
            return $this->resourceResponse($this->format($this->service->create($request->validated())), 201);
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }
    }

    public function update(int $id, UpdateSegmentRequest $request): JsonResponse
    {
        try {
            return $this->resourceResponse($this->format($this->service->update($id, $request->validated())));
        } catch (ValidationException $validationException) {
            return $this->errorResponse($validationException->getMessage(), 422, $validationException->getErrors());
        } catch (\InvalidArgumentException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'not found') ? 404 : 422;
            return $this->errorResponse($exception->getMessage(), $status);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->delete($id);
            return $this->jsonResponse(['message' => 'Segment deleted.']);
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }
}
