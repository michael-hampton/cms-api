<?php

namespace App\Controllers\Boost;

use App\Controllers\Controller;
use App\Enums\Boost\AutoBoostGoal;
use App\Enums\Boost\BoostableType;
use App\Exceptions\Boost\BoostEligibilityException;
use App\Exceptions\Boost\BoostNotFoundException;
use App\Exceptions\Boost\BoostTransitionException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Models\Boost;
use App\Models\BoostStat;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Repositories\Adverts\Boost\BoostStatRepository;
use App\Repositories\Adverts\Boost\MerchantAutoBoostSettingRepository;
use App\Repositories\Adverts\Boost\MerchantBoostStatRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\ProductRepository;
use App\Requests\Boost\CreateBoostRequest;
use App\Resources\Boost\BoostResource;
use App\Services\Adverts\Boost\BoostService;
use App\Services\Adverts\Boost\BoostSuggestionService;

class BoostController extends Controller
{
    public function __construct(
        private readonly BoostService                       $boostService,
        private readonly BoostRepository                    $boostRepository,
        private readonly ProductRepository                  $productRepository,
        private readonly ProductOfferRepository             $offerRepository,
        private readonly MerchantAutoBoostSettingRepository $autoBoostSettingRepository,
        private readonly BoostSuggestionService             $suggestionService,
        private readonly MerchantBoostStatRepository        $merchantBoostStatRepository,
        private readonly BoostStatRepository                $boostStatRepository
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/boosts
     * List boosts with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->input('status', ''),
            'boostable_type' => $request->input('boostable_type', ''),
            'context' => $request->input('context', ''),
            'merchant_id' => $request->input('merchant_id'),
            'page' => (int)$request->input('page', 1),
            'per_page' => min((int)$request->input('per_page', 20), 100),
        ];

        $result = $this->boostRepository->getAllWithFilters(array_filter($filters));

        return JsonResponse::json([
            'data' => BoostResource::collection($result['data']),
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * GET /api/boosts/{id}
     */
    public function show(int $id): JsonResponse
    {
        $boost = $this->boostRepository->find($id);

        if (!$boost) {
            return JsonResponse::json(['error' => 'Boost not found.'], 404);
        }

        return JsonResponse::json(['data' => new BoostResource($boost)]);
    }

    /**
     * POST /api/boosts
     */
    public function store(CreateBoostRequest $request): JsonResponse
    {
        try {
            $target = $this->resolveTarget(
                $request->input('boostable_type'),
                (int)$request->input('target_id')
            );

            $boost = $this->boostService->createBoost(
                target: $target,
                boostableType: $request->input('boostable_type'),
                merchantId: (int)$request->input('merchant_id'),
                context: $request->input('context'),
                startsAt: new \DateTimeImmutable($request->input('starts_at')),
                endsAt: new \DateTimeImmutable($request->input('ends_at')),
                multiplier: (float)$request->input('multiplier'),
                currency: $request->input('currency', 'GBP'),
                paymentReference: $request->input('payment_reference'),
                campaignOverride: $request->input('campaign_override'),
            );

            return JsonResponse::json(['data' => new BoostResource($boost)], 201);

        } catch (BoostEligibilityException $e) {
            return JsonResponse::json(['error' => $e->getMessage()], 422);
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::json(['error' => $e->getMessage()], 422);
        }
    }

    private function resolveTarget(string $boostableType, int $targetId): \App\Contracts\Boost\BoostableInterface
    {
        $target = match (BoostableType::from($boostableType)) {
            BoostableType::Product => $this->productRepository->find($targetId),
            BoostableType::Offer => $this->offerRepository->find($targetId),
        };

        if (!$target) {
            throw new BoostEligibilityException(
                ucfirst($boostableType) . " [{$targetId}] not found."
            );
        }

        return $target;
    }

    /**
     * POST /api/boosts/{id}/activate
     */
    public function activate(int $id): JsonResponse
    {
        try {
            $boost = $this->boostService->activateBoost($id);
            return JsonResponse::json(['data' => new BoostResource($boost)]);
        } catch (BoostNotFoundException $e) {
            return JsonResponse::json(['error' => $e->getMessage()], 404);
        } catch (BoostTransitionException $e) {
            return JsonResponse::json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/boosts/{id}/expire
     */
    public function expire(int $id): JsonResponse
    {
        try {
            $boost = $this->boostService->expireBoost($id);
            return JsonResponse::json(['data' => new BoostResource($boost)]);
        } catch (BoostNotFoundException $e) {
            return JsonResponse::json(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * POST /api/boosts/{id}/cancel
     */
    public function cancel(int $id): JsonResponse
    {
        try {
            $boost = $this->boostService->cancelBoost($id);
            return JsonResponse::json(['data' => new BoostResource($boost)]);
        } catch (BoostNotFoundException $e) {
            return JsonResponse::json(['error' => $e->getMessage()], 404);
        } catch (BoostTransitionException $e) {
            return JsonResponse::json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/boosts/{id}/pause
     */
    public function pause(int $id): JsonResponse
    {
        try {
            $boost = $this->boostService->pauseBoost($id);
            return JsonResponse::json(['data' => new BoostResource($boost)]);
        } catch (BoostNotFoundException $e) {
            return JsonResponse::json(['error' => $e->getMessage()], 404);
        } catch (BoostTransitionException $e) {
            return JsonResponse::json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/boosts/{id}/resume
     */
    public function resume(int $id): JsonResponse
    {
        try {
            $boost = $this->boostService->resumeBoost($id);
            return JsonResponse::json(['data' => new BoostResource($boost)]);
        } catch (BoostNotFoundException $e) {
            return JsonResponse::json(['error' => $e->getMessage()], 404);
        } catch (BoostTransitionException $e) {
            return JsonResponse::json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/boosts/{id}/stats
     */
    public function stats(int $id): JsonResponse
    {
        $boost = $this->boostRepository->find($id);

        if (!$boost) {
            return JsonResponse::json(['error' => 'Boost not found.'], 404);
        }

        $stat = $this->boostStatRepository->findByBoost($id);

        return JsonResponse::json([
            'data' => [
                'boost_id' => $id,
                'impressions' => $stat?->impressions ?? 0,
                'clicks' => $stat?->clicks ?? 0,
                'conversions' => $stat?->conversions ?? 0,
                'spend_attributed' => $stat?->spend_attributed ?? 0.0,
                'ctr' => $stat?->ctr() ?? 0.0,
                'conversion_rate' => $stat?->conversionRate() ?? 0.0,
                'last_updated_at' => $stat?->last_aggregated_at,
                'limit_breached' => $boost->limit?->pause_on_breach && $boost->isPaused(),
                'breach_message' => $boost->isPaused() && $boost->limit
                    ? "This boost was automatically paused because it reached its {$this->resolveLimitType($boost, $stat)} limit. You can resume it or adjust your limits."
                    : null,
            ],
        ]);
    }

    private function resolveLimitType(Boost $boost, ?BoostStat $stat): string
    {
        if (!$boost->limit || !$stat) {
            return 'unknown';
        }

        if ($boost->limit->hasSpendLimit() && $stat->spend_attributed >= $boost->limit->max_spend) {
            return 'spend';
        }
        if ($boost->limit->hasClickLimit() && $stat->clicks >= $boost->limit->max_clicks) {
            return 'click';
        }
        if ($boost->limit->hasImpressionLimit() && $stat->impressions >= $boost->limit->max_impressions) {
            return 'impression';
        }

        return 'unknown';
    }

    /**
     * GET /api/merchants/{merchantId}/boost-stats
     */
    public function merchantStats(int $merchantId): JsonResponse
    {
        $stat = $this->merchantBoostStatRepository->findByMerchant($merchantId);

        return JsonResponse::json([
            'data' => [
                'merchant_id' => $merchantId,
                'total_impressions' => $stat?->total_impressions ?? 0,
                'total_clicks' => $stat?->total_clicks ?? 0,
                'total_conversions' => $stat?->total_conversions ?? 0,
                'total_spend_attributed' => $stat?->total_spend_attributed ?? 0.0,
                'last_updated_at' => $stat?->last_aggregated_at,
            ],
        ]);
    }

    /**
     * GET /api/merchants/{id}/boost-suggestions
     */
    public function suggestions(int $merchantId, Request $request): JsonResponse
    {
        $goal = $request->input('goal', AutoBoostGoal::MaximiseRevenue->value);

        try {
            AutoBoostGoal::from($goal);
        } catch (\ValueError) {
            return JsonResponse::json(['error' => 'Invalid goal.'], 422);
        }

        $suggestions = $this->suggestionService->getSuggestions($merchantId, $goal);

        return JsonResponse::json([
            'data' => array_map(fn($s) => $s->toArray(), $suggestions),
        ]);
    }

    /**
     * GET /api/merchants/{id}/auto-boost/preview
     */
    public function autoBoostPreview(int $merchantId): JsonResponse
    {
        $plan = $this->autoBoostService->preview($merchantId);
        return JsonResponse::json(['data' => $plan->toArray()]);
    }

    /**
     * POST /api/merchants/{id}/auto-boost/settings
     */
    public function saveAutoBoostSettings(int $merchantId, Request $request): JsonResponse
    {
        $data = $request->only(['monthly_budget', 'goal', 'contexts_allowed', 'is_enabled']);

        try {
            AutoBoostGoal::from($data['goal'] ?? '');
        } catch (\ValueError) {
            return JsonResponse::json(['error' => 'Invalid goal.'], 422);
        }

        $setting = $this->autoBoostSettingRepository->upsert($merchantId, array_merge($data, [
            'merchant_id' => $merchantId,
        ]));

        return JsonResponse::json(['data' => $setting->toArray()]);
    }

    public function boostPage()
    {
        return $this->view('member-portal/boost-management');
    }

    /**
     * GET /api/merchants/{id}/auto-boost/settings
     */
    public function getAutoBoostSettings(int $merchantId): JsonResponse
    {
        $setting = $this->autoBoostSettingRepository->findByMerchant($merchantId);

        if (!$setting) {
            return JsonResponse::json(['data' => null]);
        }

        return JsonResponse::json([
            'data' => [
                'is_enabled' => $setting->is_enabled,
                'monthly_budget' => $setting->monthly_budget,
                'goal' => $setting->goal,
                'contexts_allowed' => $setting->contexts_allowed,
            ],
        ]);
    }

    /**
     * GET /api/merchants/{merchantId}/products/search?q=
     */
    public function searchMerchantProducts(int $merchantId, Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));

        if (strlen($query) < 1) {
            return JsonResponse::json(['data' => []]);
        }

        $products = Product::whereHas('merchants', fn($q) => $q->where('merchant_id', $merchantId)
        )
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->where(fn($q) => $q->where('name', 'LIKE', "%{$query}%")
                ->orWhere('slug', 'LIKE', "%{$query}%")
            )
            ->with(['brand'])
            ->limit(15)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku ?? null,
                'price' => $p->price,
                'stock_quantity' => $p->stock_quantity,
                'brand' => $p->brand?->name,
            ]);

        return JsonResponse::json(['data' => $products]);
    }

    /**
     * GET /api/merchants/{merchantId}/offers/search?q=
     */
    public function searchMerchantOffers(int $merchantId, Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));

        if (strlen($query) < 1) {
            return JsonResponse::json(['data' => []]);
        }

        $now = now_datetime()->format('Y-m-d H:i:s');

        $offers = ProductOffer::whereHas('product', fn($q) => $q->whereHas('merchants', fn($q2) => $q2->where('merchant_id', $merchantId)
        )
        )
            ->where('is_active', true)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->where(fn($q) => $q->where('title', 'LIKE', "%{$query}%")
                ->orWhereHas('product', fn($q2) => $q2->where('name', 'LIKE', "%{$query}%")
                )
            )
            ->with(['product'])
            ->limit(15)
            ->get()
            ->map(fn($o) => [
                'id' => $o->id,
                'name' => $o->title ?? $o->product?->name,
                'price' => $o->sale_price,
                'discount_percent' => $o->original_price > 0
                    ? round((($o->original_price - $o->sale_price) / $o->original_price) * 100, 1)
                    : 0,
            ]);

        return JsonResponse::json(['data' => $offers]);
    }
}