<?php

namespace App\Controllers\Recommendations;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Services\Recommendations\ProductRecommendationService;

class RecommendationController extends Controller
{
    public function __construct(
        private readonly ProductRecommendationService $recommendationService,
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/recommendations/products
     *
     * Returns personalised recommendations for authenticated members,
     * or popular products for guests. Accepts an optional ?exclude= param
     * (comma-separated product IDs already visible on the page).
     */
    public function products(Request $request): mixed
    {
        $limit = min((int)($request->input('limit', 8)), 24);
        $excludeIds = array_filter(
            array_map('intval', explode(',', $request->input('exclude', '')))
        );

        /** @var Member|null $member */
        $member = auth()->user();
        $siteId = SiteContext::getId();

        if ($member instanceof Member) {
            $products = $this->recommendationService->getRecommendedProducts($member, $siteId, $limit);
        } else {
            $products = $this->recommendationService->getPopularProducts($siteId, $limit, $excludeIds);
        }

        $isPersonalised = $member instanceof Member;

        return $this->resourceResponse([
            'success' => true,
            'personalised' => $isPersonalised,
            'heading' => $isPersonalised ? 'Recommended For You' : 'Popular Right Now',
            'products' => $products->map(fn($p) => $this->formatProduct($p))->values()->toArray(),
        ]);
    }

    private function formatProduct($product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'sale_price' => $product->sale_price,
            'image' => $product->main_image_url ?? $product->images->first()?->url ?? null,
            'brand' => $product->brand?->name,
            'average_rating' => $product->average_rating ?? 0,
            'review_count' => $product->approvedReviews?->count() ?? 0,
            'discount_percentage' => $product->sale_price && $product->price > 0
                ? (int)round((($product->price - $product->sale_price) / $product->price) * 100)
                : 0,
        ];
    }
}