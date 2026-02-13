<?php

namespace App\Controllers\Product;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Repositories\Product\ProductStockAlertRepository;

class StockAlertController extends Controller
{
    public function __construct(
        private readonly ProductStockAlertRepository $alertRepository
    )
    {
        parent::__construct();
    }

    public function create(Request $request): JsonResponse
    {
        /**
         * [
         * 'product_id' => 'required|integer',
         * 'email' => 'required_without:user_id|email',
         * ]
         */

        $validated = $request->all();

        $userId = auth()->id();
        $email = $userId ? null : $validated['email'];

        // Check for existing alert
        $existing = $this->alertRepository->findByProductAndUser(
            $validated['product_id'],
            $userId,
            $email
        );

        if ($existing) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'You are already subscribed to alerts for this product',
            ]);
        }

        $alert = $this->alertRepository->create([
            'product_id' => $validated['product_id'],
            'user_id' => $userId,
            'email' => $email,
        ]);

        return $this->resourceResponse([
            'success' => true,
            'message' => 'You will be notified when this product is back in stock',
        ]);
    }
}