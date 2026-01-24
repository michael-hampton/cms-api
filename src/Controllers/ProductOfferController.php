<?php
// src/Controllers/ProductOfferController.php

namespace App\Controllers;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Requests\CreateProductOfferRequest;
use App\Requests\UpdateProductOfferRequest;
use App\Services\Product\ProductOfferService;
use Exception;

class ProductOfferController extends Controller
{
    public function __construct(
        private readonly ProductOfferService $offerService
    )
    {
        parent::__construct();
    }

    public function index(int $productId, string $siteName): JsonResponse
    {
        try {
            $offers = $this->offerService->getActiveOffersForProduct($productId);

            return $this->resourceResponse([
                'success' => true,
                'items' => $offers->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function categoryOffers(int $categoryId, string $siteName): JsonResponse
    {
        try {
            $offers = $this->offerService->getActiveOffersForCategory($categoryId);

            return $this->resourceResponse([
                'success' => true,
                'items' => $offers->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(int $productId, CreateProductOfferRequest $request, string $siteName): JsonResponse
    {
        try {
            $data = array_merge($request->validated(), ['product_id' => $productId]);

            $offer = $this->offerService->createOffer($data);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Offer created successfully',
                'offer' => $offer->toArray()
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $productId, int $offerId, UpdateProductOfferRequest $request, string $siteName): JsonResponse
    {
        try {
            $offer = $this->offerService->updateOffer($offerId, $request->validated());

            if (!$offer) {
                return $this->jsonResponse([
                    'message' => 'Offer not found'
                ], 404);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Offer updated successfully',
                'offer' => $offer->toArray()
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $productId, int $offerId, string $siteName): JsonResponse
    {
        try {
            $deleted = $this->offerService->deleteOffer($offerId);

            if (!$deleted) {
                return $this->jsonResponse([
                    'message' => 'Offer not found'
                ], 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Offer deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}