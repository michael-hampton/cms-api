<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Models\SuspensionReason;
use App\Requests\Subscription\BusinessDecisions\StoreSuspensionReasonRequest;
use App\Requests\Subscription\BusinessDecisions\UpdateSuspensionReasonRequest;
use App\Services\Subscriptions\BusinessDecisions\SuspensionReasonAdminService;
use InvalidArgumentException;

class SuspensionReasonAdminController extends Controller
{
    public function __construct(private readonly SuspensionReasonAdminService $service)
    {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->list((int) $request->get('page', 1), (int) $request->get('per_page', 20), $request->get('search') ?: null, $request->get('sort_by', 'sort_order'), $request->get('sort_order', 'asc'));

        return $this->resourceResponse(['data' => $result['data']->map(fn (SuspensionReason $reason) => $this->format($reason))->toArray(), 'meta' => $result['pagination']]);
    }

    public function show(int $id): JsonResponse
    {
        try {
            return $this->resourceResponse($this->format($this->service->find($id)));
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }

    public function store(StoreSuspensionReasonRequest $request): JsonResponse
    {
        try {
            return $this->resourceResponse($this->format($this->service->create($request->validated())), 201);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }
    }

    public function update(int $id, UpdateSuspensionReasonRequest $request): JsonResponse
    {
        try {
            return $this->resourceResponse($this->format($this->service->update($id, $request->validated())));
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), str_contains(strtolower($exception->getMessage()), 'not found') ? 404 : 422);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->deactivate($id);
            return $this->jsonResponse(['message' => 'Suspension reason deactivated.']);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }

    private function format(SuspensionReason $reason): array
    {
        return ['id' => $reason->id, 'code' => $reason->code, 'label' => $reason->label, 'requires_note' => (bool) $reason->requires_note, 'is_active' => (bool) $reason->is_active, 'sort_order' => (int) $reason->sort_order];
    }
}
