<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Actions\PublicContent\GetPublicContentTypeListingAction;
use App\Controllers\Controller;
use App\Enums\PublicContent\PublicDirectoryType;
use App\Factories\PublicContent\ListingFilterDataFactory;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Services\PublicContent\Directory\PublicDirectoryListingConfigProvider;

final class PublicContentTypeListingController extends Controller
{
    public function __construct(
        private readonly GetPublicContentTypeListingAction $listings,
        private readonly PublicDirectoryListingConfigProvider $listingConfig,
        private readonly ListingFilterDataFactory $filterFactory,
    ) {
        parent::__construct();
    }

    public function buyingGuides(Request $request): JsonResponse
    {
        return $this->list(PublicDirectoryType::BuyingGuide, 'newest', $request);
    }

    public function reviews(Request $request): JsonResponse
    {
        return $this->list(PublicDirectoryType::Review, 'newest', $request);
    }

    private function list(PublicDirectoryType $type, string $defaultSort, Request $request): JsonResponse
    {
        $site = SiteContext::get();
        $config = $this->listingConfig->forSite($site, $type);
        $filter = $this->filterFactory->fromQueryParams($request->all(), $config, $defaultSort);

        return $this->resourceResponse([
            'data' => $this->listings->list($type, SiteContext::getId(), $site, SiteContext::slug(), $filter),
        ]);
    }
}