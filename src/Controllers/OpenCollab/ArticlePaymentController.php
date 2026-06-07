<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Exceptions\OpenCollab\DuplicatePurchaseException;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Repositories\Cms\Pages\PageRepository;
use App\Requests\OpenCollab\InitiatePaymentRequest;
use App\Services\OpenCollab\ArticleAccessService;
use App\Services\OpenCollab\ArticlePaymentService;

class ArticlePaymentController extends Controller
{
    public function __construct(
        private readonly ArticlePaymentService $paymentService,
        private readonly PageRepository        $pageRepository,
        private readonly ArticleAccessService $articleAccessService,
    )
    {
        parent::__construct();
    }

    /**
     * POST /api/{site}/open-collab/pages/{id}/purchase
     * Creates a Stripe PaymentIntent. The frontend uses the returned client_secret
     * to complete the payment. Access is NOT granted here.
     */
    public function initiate(InitiatePaymentRequest $request, int $pageId): JsonResponse
    {
        $page = $this->pageRepository->find($pageId);

        if (!$page || $page->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Page not found.', 404);
        }

        try {
            $data = $request->validated();
            $result = $this->paymentService->initiatePayment(
                page: $page,
                userId: Auth::id(), // null for unauthenticated guests
                email: $data['email'],
            );


            // try with http://localhost:5001/guitar-world/test-55
            if(!empty($result['payment']->stripe_payment_intent_id)) { //todo this will is tmporary and will be covered by webhook
                $this->articleAccessService->grantAccessFromPayment($result['payment']->stripe_payment_intent_id);
            }

            return $this->jsonResponse([
                'client_secret' => $result['client_secret'],
                'payment_id' => $result['payment']->id,
            ], 201);
        } catch (ValidationException $validationException) {
            return $this->errorResponse('Validation failed', 422, $validationException->getErrors());
        } catch (DuplicatePurchaseException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}