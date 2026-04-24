<?php

declare(strict_types=1);

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Services\MemberInsights\Newsletters\Recommendations\NewsletterRecommendationService;
use App\Services\MemberInsights\Newsletters\Suppression\NewsletterSuppressionService;

/**
 * GET /api/{site}/member/newsletters/recommendations
 *
 * Returns ranked newsletter recommendations for the authenticated member,
 * with subscribed newsletters already suppressed.
 *
 * Response shape:
 * {
 *   "data": [
 *     {
 *       "newsletter_id": 12,
 *       "title": "Tech Weekly",
 *       "reason": "Because you subscribe to Laravel Weekly — same topic",
 *       "score": 20
 *     }
 *   ]
 * }
 *
 * Returns an empty data array (not an error) when no recommendations exist.
 * The frontend uses this to conditionally hide the section.
 */
class NewsletterRecommendationsController extends Controller
{
    public function __construct(
        private readonly NewsletterSuppressionService    $suppressionService,
        private readonly NewsletterRecommendationService $recommendationService,
    )
    {
        parent::__construct();
    }

    public function __invoke(Request $request): JsonResponse
    {
        $member = MemberAuth::getMember();

        $suppression = $this->suppressionService->buildSuppressionSet($member, SiteContext::getId());

        $recommendations = $this->recommendationService->recommend(
            member: $member,
            suppression: $suppression,
            siteId: SiteContext::getId(),
        );

        return $this->resourceResponse([
            'data' => array_map(fn($result) => [
                'newsletter_id' => $result->newsletter->id,
                'title' => $result->newsletter->title,
                'reason' => $result->reason,
                'score' => $result->score,
            ], $recommendations),
        ]);
    }
}