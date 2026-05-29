<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Services\MemberInsights\Segmentation\RenewalOfferFilter;
use App\Services\MemberInsights\Segmentation\RenewalOfferResolver;

class RenewalOfferApiController extends Controller
{
    public function __construct(
        private readonly RenewalOfferResolver $resolver,
    ) {
        parent::__construct();
    }

    /**
     * GET /api/subscriptions/{id}/renewal-offers
     *
     * Optional query params: edition, region, payment_type, active_date
     */
    public function index(int $subscriptionId, Request $request): JsonResponse
    {
        try {
            $filter = RenewalOfferFilter::fromArray($request->only([
                'edition',
                'region',
                'payment_type',
                'active_date',
            ]));

            $result = $this->resolver->resolve($subscriptionId, $filter);

            return $this->resourceResponse($result);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }
}