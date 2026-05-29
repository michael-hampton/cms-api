<?php

namespace App\Controllers\Vouchers;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Repositories\Vouchers\VoucherRepository;
use App\Requests\Voucher\CreateSubscriptionVoucherRequest;
use App\Requests\Voucher\UpdateSubscriptionVoucherRequest;
use App\Resources\SubscriptionVoucherResource;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteriaParser;
use App\Search\SearchEngine;
use App\Services\Vouchers\VoucherService;
use Exception;

/**
 * Handles CRUD for subscription-specific vouchers.
 *
 * Routes (all scoped under /api/{site}/subscription-vouchers):
 *
 *   GET    /                    index      — paginated list
 *   POST   /                    store      — create
 *   GET    /{id}                show       — single record
 *   PUT    /{id}                update     — full update
 *   DELETE /{id}                destroy    — delete (blocked if used)
 *   GET    /{id}/deletable      checkDelete
 *
 * Response envelope always matches the Angular SubscriptionVouchersService shape:
 *   { success: true, data: { items: [], pagination: {} } }   — list
 *   { success: true, data: { voucher: {} } }                 — single
 *
 * This controller does NOT re-implement business logic. All writes go through
 * VoucherService which owns transactions, Stripe coupon reset detection, and
 * subscription plan sync.
 */
class SubscriptionVoucherController extends Controller
{
    public function __construct(
        private readonly VoucherService    $voucherService,
        private readonly VoucherRepository $voucherRepository,
    ) {
        parent::__construct();
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function index(Request $request, string $site): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $site);

            // Force the subscription scope regardless of what the caller passes —
            // this endpoint must never return order-only vouchers.
            $criteria->addFilter('applies_to_subscriptions', true);

            $config       = SearchConfigurationFactory::create('subscription_voucher');
            $searchEngine = new SearchEngine($config);

            $query  = \App\Models\Voucher::query();
            $result = $searchEngine->search($query, $criteria);

            $result->transform(fn($voucher) => (new SubscriptionVoucherResource($voucher))->toArray());

            $paged = $result->toArray();

            // Angular reads response.data.items and response.data.pagination
            return $this->jsonResponse([
                'items'      => $paged['data'],
                'pagination' => $paged['pagination'],
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function store(CreateSubscriptionVoucherRequest $request, string $site): JsonResponse
    {
        try {
            $validated = $request->validated();

            $voucher = $this->voucherService->create($validated);

            return $this->jsonResponse(
                ['voucher' => (new SubscriptionVoucherResource($voucher))->toArray()],
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(int $id, string $site): JsonResponse
    {
        try {
            $voucher = $this->voucherRepository->find($id);

            if (!$voucher) {
                return $this->errorResponse('Voucher not found', 404);
            }

            return $this->jsonResponse([
                'voucher' => (new SubscriptionVoucherResource($voucher))->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(int $id, UpdateSubscriptionVoucherRequest $request, string $site): JsonResponse
    {
        try {
            $validated = $request->validated();

            $voucher = $this->voucherService->update($id, $validated);

            if (!$voucher) {
                return $this->errorResponse('Voucher not found', 404);
            }

            return $this->jsonResponse([
                'voucher' => (new SubscriptionVoucherResource($voucher))->toArray(),
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function destroy(int $id, string $site): JsonResponse
    {
        try {
            $result = $this->voucherService->delete($id);

            if (!$result) {
                return $this->errorResponse('Voucher not found', 404);
            }

            return $this->jsonResponse(['message' => 'Voucher deleted successfully']);
        } catch (Exception $e) {
            // VoucherService throws VoucherNotDeletableException (extends Exception)
            // when usage_count > 0; surface that as a 400 so the frontend can
            // display the reason rather than a generic 500.
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    // ── Delete eligibility check ──────────────────────────────────────────────

    /**
     * Returns whether the voucher can be safely deleted.
     *
     * Angular calls this before showing the delete confirmation modal:
     *   { data: { can_delete: bool, usage_count: int, requires_confirmation: bool } }
     */
    public function checkDelete(int $id, string $site): JsonResponse
    {
        try {
            $result = $this->voucherService->checkDeletable($id);
            return $this->jsonResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}