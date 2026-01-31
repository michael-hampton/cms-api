<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Models\ProductOffer;
use App\Services\Offers\ProductOfferService;
use Exception;

class OfferListController extends Controller
{
    public function __construct(private readonly ProductOfferService $offerService)
    {
        parent::__construct();
    }

    /**
     * Display product offers listing page
     */
    public function index(): mixed
    {
        return $this->view('product-offers/index');
    }

    /**
     * Display single product offer detail page
     */
    public function show(int $offerId): mixed
    {
        try {
            $offer = ProductOffer::with(['product', 'merchant'])
                ->where('id', $offerId)
                ->where('status', 'published')
                ->where('is_active', true)
                ->first();

            if (!$offer) {
                return $this->errorResponse('Member ID is required', 404);
            }

            return $this->view('product-offers/detail', [
                'offer' => $offer
            ]);
        } catch (Exception $e) {
            return $this->errorResponse('does not exist', 500);
        }
    }

    /**
     * Search product offers
     * GET /api/product-offers/search
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->get('q') ?? $request->get('search'),
                'status' => 'published',
                'is_active' => true,
                'category' => $request->get('category'),
                'merchant_id' => $request->get('merchant_id'),
                'min_discount' => $request->get('min_discount'),
                'min_price' => $request->get('min_price'),
                'max_price' => $request->get('max_price'),
                'sort_by' => $request->get('sort_by', 'created_at'),
                'sort_order' => $request->get('sort_order', 'desc'),
                'per_page' => $request->get('per_page', 20),
                'page' => $request->get('page', 1),
            ];

            $results = $this->offerService->getOffersForWeb($filters);

            return $this->jsonResponse([
                'success' => true,
                'offers' => $results
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}