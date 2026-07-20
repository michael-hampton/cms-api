<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Models\CancellationReason;
use App\Requests\Subscription\BusinessDecisions\StoreCancellationReasonRequest;
use App\Requests\Subscription\BusinessDecisions\UpdateCancellationReasonRequest;
use App\Services\Subscriptions\BusinessDecisions\CancellationReasonAdminService;
use InvalidArgumentException;

class CancellationReasonAdminController extends Controller
{
    public function __construct(
        private readonly CancellationReasonAdminService $service,
    ) {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->list(
            page: (int) $request->get('page', 1),
            perPage: (int) $request->get('per_page', 20),
            search: $request->get('search') ?: null,
            sortBy: $request->get('sort_by', 'sort_order'),
            sortOrder: $request->get('sort_order', 'asc'),
        );

        return $this->resourceResponse([
            'data' => $result['data']->map(fn (CancellationReason $reason) => $this->format($reason))->toArray(),
            'meta' => $result['pagination'],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        try {
            return $this->resourceResponse($this->format($this->service->find($id)));
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }

    public function store(StoreCancellationReasonRequest $request): JsonResponse
    {
        try {
            return $this->resourceResponse($this->format($this->service->create($request->validated())), 201);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }
    }

    public function update(int $id, UpdateCancellationReasonRequest $request): JsonResponse
    {
        try {
            return $this->resourceResponse($this->format($this->service->update($id, $request->validated())));
        } catch (InvalidArgumentException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'not found') ? 404 : 422;
            return $this->errorResponse($exception->getMessage(), $status);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->deactivate($id);
            return $this->jsonResponse(['message' => 'Cancellation reason deactivated.']);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }

    private function format(CancellationReason $reason): array
    {
        return [
            'id' => $reason->id,
            'code' => $reason->code,
            'label' => $reason->label,
            'requires_note' => (bool) $reason->requires_note,
            'is_active' => (bool) $reason->is_active,
            'sort_order' => (int) $reason->sort_order,
        ];
    }
}
