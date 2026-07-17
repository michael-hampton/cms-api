<?php

declare(strict_types=1);

namespace App\Controllers\Subscription;

use App\Controllers\Concerns\RequiresSitePermission;
use App\Controllers\Controller;
use App\Enums\Subscriptions\PrintVendorConnectionType;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\ResourceCollection;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\PrintVendorConnectionRepository;
use App\Requests\Subscription\BasePrintVendorConnectionRequest;
use App\Requests\Subscription\PrintVendorConnectionStoreRequest;
use App\Requests\Subscription\PrintVendorConnectionUpdateRequest;
use App\Resources\PrintVendorConnectionResource;
use App\Services\Subscriptions\PrintVendorConnectionService;
use InvalidArgumentException;
use Throwable;

/**
 * Admin REST surface for managing print/label vendor SFTP connections.
 *
 * Routes (registered under the authenticated /api/{site} group, matching
 * the LabelRunReportController / print-runs section):
 *
 *   GET    /print-vendor-connections               index
 *   GET    /print-vendor-connections/{id}           show
 *   POST   /print-vendor-connections                store
 *   PUT    /print-vendor-connections/{id}           update
 *   DELETE /print-vendor-connections/{id}           destroy (soft: is_active=false)
 *   POST   /print-vendor-connections/{id}/test       testConnection
 *
 * Connections are not site-scoped (the print/label pipeline they serve
 * has no site concept — see LabelRun/PrintBatch/PrintFulfillment), but
 * endpoints still live under the site-prefixed, permission-checked group
 * for consistency with the rest of the print admin surface (matching
 * WorkflowRunController, which is likewise global data under a site URL).
 */
class PrintVendorConnectionController extends Controller
{
    use RequiresSitePermission;

    private const PERMISSION_VIEW = 'print_vendor_connections.view';
    private const PERMISSION_MANAGE = 'print_vendor_connections.manage';

    public function __construct(
        private readonly PrintVendorConnectionService    $service,
        private readonly PrintVendorConnectionRepository $repository,
    ) {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->requireSitePermission(self::PERMISSION_VIEW)) {
            return $response;
        }

        $type = $request->query('connection_type');

        if ($type !== null && !PrintVendorConnectionType::tryFrom($type)) {
            return $this->errorResponse('Invalid connection_type value', 422);
        }

        $connections = $type
            ? $this->service->listForType(PrintVendorConnectionType::from($type))
            : $this->service->list();

        $collection = new ResourceCollection($connections->toArray(), PrintVendorConnectionResource::class);

        return $this->resourceResponse($collection->toArray());
    }

    public function show(int $id): JsonResponse
    {
        if ($response = $this->requireSitePermission(self::PERMISSION_VIEW)) {
            return $response;
        }

        $connection = $this->repository->find($id);

        if (!$connection) {
            return $this->errorResponse('Print vendor connection not found', 404);
        }

        return $this->resourceResponse([
            'connection' => PrintVendorConnectionResource::make($connection)->toArray(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->requireSitePermission(self::PERMISSION_MANAGE)) {
            return $response;
        }

        $formRequest = PrintVendorConnectionStoreRequest::createFromRequest($request);

        if ($formRequest->fails()) {
            return $this->errorResponse('Validation failed', 422, $formRequest->getValidationErrors());
        }

        $data = $this->coerceBooleans($formRequest->validated());

        try {
            $connection = $this->service->create($data);

            return $this->resourceResponse([
                'connection' => PrintVendorConnectionResource::make($connection)->toArray(),
            ], 201);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            Logger::error('Failed to create print vendor connection', ['error' => $exception->getMessage()]);

            return $this->errorResponse('Failed to create print vendor connection', 500);
        }
    }

    public function update(int $id, Request $request): JsonResponse
    {
        if ($response = $this->requireSitePermission(self::PERMISSION_MANAGE)) {
            return $response;
        }

        $formRequest = PrintVendorConnectionUpdateRequest::createFromRequest($request);

        if ($formRequest->fails()) {
            return $this->errorResponse('Validation failed', 422, $formRequest->getValidationErrors());
        }

        $data = $this->coerceBooleans($formRequest->validated());

        try {
            $connection = $this->service->update($id, $data);

            return $this->resourceResponse([
                'connection' => PrintVendorConnectionResource::make($connection)->toArray(),
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            Logger::error('Failed to update print vendor connection', [
                'connection_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return $this->errorResponse('Failed to update print vendor connection', 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        if ($response = $this->requireSitePermission(self::PERMISSION_MANAGE)) {
            return $response;
        }

        try {
            $connection = $this->service->deactivate($id);

            return $this->resourceResponse([
                'message' => 'Print vendor connection deactivated.',
                'connection' => PrintVendorConnectionResource::make($connection)->toArray(),
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            Logger::error('Failed to deactivate print vendor connection', [
                'connection_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return $this->errorResponse('Failed to deactivate print vendor connection', 500);
        }
    }

    /**
     * Live-tests the stored credentials against the real SFTP server and
     * persists the result onto the connection row.
     */
    public function testConnection(int $id): JsonResponse
    {
        if ($response = $this->requireSitePermission(self::PERMISSION_MANAGE)) {
            return $response;
        }

        try {
            $result = $this->service->testConnection($id);

            return $this->resourceResponse([
                'success' => $result['success'],
                'message' => $result['message'],
            ], $result['success'] ? 200 : 422);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        } catch (Throwable $exception) {
            Logger::error('Failed to test print vendor connection', [
                'connection_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return $this->errorResponse('Failed to test print vendor connection', 500);
        }
    }

    /**
     * FormRequest::validated() returns raw input values, not cast ones —
     * coerce the known boolean fields explicitly, matching
     * ReplacementPolicyController's pattern.
     */
    private function coerceBooleans(array $data): array
    {
        foreach (BasePrintVendorConnectionRequest::booleanFields() as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $data;
    }
}