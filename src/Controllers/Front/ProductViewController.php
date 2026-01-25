<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Models\Page;
use App\Services\Members\WishlistService;
use App\Services\Product\ProductService;
use App\Services\Product\ReviewService;
use App\Services\Url\UrlResolutionResult;
use function App\Controllers\abort;
use function App\Controllers\response;
use function App\Controllers\view;

class ProductViewController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ReviewService $reviewService,
        private readonly WishlistService $wishlistService
    ) {}

    public function show(Page $page, UrlResolutionResult $result, Request $request)
    {
        $product = $this->productService->getProductWithVariants($page->product_id);

        if (!$product || !$product->is_active) {
            abort(404);
        }

        // Handle AJAX requests for variant data
        if ($request->ajax()) {
            return $this->handleAjaxRequest($request, $product);
        }

        $reviews = $this->reviewService->getProductReviews($product->id);
        $relatedProducts = $this->productService->getRelatedProducts($product, 8);
        $recentlyViewed = $this->productService->getRecentlyViewedProducts();

        // Track product view
        $this->productService->trackView($product);

        // Check if user has this in wishlist
        $inWishlist = auth()->check()
            ? $this->wishlistService->isInWishlist(auth()->user(), $product)
            : false;

        return view('products.show', [
            'page' => $page,
            'product' => $product,
            'variants' => $product->variants,
            'reviews' => $reviews,
            'relatedProducts' => $relatedProducts,
            'recentlyViewed' => $recentlyViewed,
            'inWishlist' => $inWishlist,
            'canonical_url' => $result->canonicalUrl,
            'structuredData' => $this->productService->generateStructuredData($product),
            'meta_tags' => $this->buildProductMetaTags($product),
        ]);
    }

    private function handleAjaxRequest(Request $request, $product)
    {
        if ($request->has('variant_id')) {
            $variant = $this->productService->getVariant($request->variant_id);
            return response()->json([
                'price' => $variant->price,
                'stock' => $variant->stock_quantity,
                'images' => $variant->images,
            ]);
        }

        return response()->json(['error' => 'Invalid request'], 400);
    }

    private function buildProductMetaTags($product): array
    {
        return [
            ['property' => 'og:type', 'content' => 'product'],
            ['property' => 'og:title', 'content' => $product->name],
            ['property' => 'og:description', 'content' => $product->short_description],
            ['property' => 'og:image', 'content' => $product->main_image_url],
            ['property' => 'product:price:amount', 'content' => $product->price],
            ['property' => 'product:price:currency', 'content' => 'USD'],
            ['property' => 'product:availability', 'content' => $product->in_stock ? 'instock' : 'oos'],
        ];
    }
}