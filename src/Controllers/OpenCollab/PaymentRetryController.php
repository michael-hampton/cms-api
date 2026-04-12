<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Services\OpenCollab\PaymentRetryService;

/**
 * Routes:
 *   POST /api/{site}/open-collab/payments/{id}/retry — authenticated user retries a failed payment
 */
class PaymentRetryController extends Controller
{
    public function __construct(
        private readonly PaymentRetryService $retryService,
    )
    {
        parent::__construct();
    }

    /**
     * POST /api/{site}/open-collab/payments/{id}/retry
     *
     * Returns a client_secret for the frontend to re-run Stripe Elements.
     */
    public function retry(int $id): JsonResponse
    {
        $userId = Auth::id();

        if (!$userId) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        try {
            $result = $this->retryService->retry(
                paymentId: $id,
                userId: $userId,
            );

            return $this->jsonResponse([
                'client_secret' => $result['client_secret'],
                'payment_id' => $result['payment_id'],
            ]);
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }
}