<?php
// app/Controllers/Api/V1/PublicDirectoryController.php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Actions\PublicContent\GetPublicDirectoryAction;
use App\Controllers\Controller;
use App\Enums\PublicContent\PublicDirectoryType;
use App\Factories\PublicContent\ListingFilterDataFactory;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Services\PublicContent\Directory\PublicDirectoryListingConfigProvider;

final class PublicDirectoryController extends Controller
{
    public function __construct(
        private readonly GetPublicDirectoryAction $directories,
        private readonly PublicDirectoryListingConfigProvider $listingConfig,
        private readonly ListingFilterDataFactory $filterFactory,
    ) {
        parent::__construct();
    }

    public function authors(Request $request): JsonResponse
    {
        return $this->index(PublicDirectoryType::Author, 'name_asc', $request);
    }

    public function author(string $slug): JsonResponse
    {
        return $this->show(PublicDirectoryType::Author, $slug, 'newest');
    }

    public function categories(Request $request): JsonResponse
    {
        return $this->index(PublicDirectoryType::Category, 'name_asc', $request);
    }

    public function category(string $slug): JsonResponse
    {
        return $this->show(PublicDirectoryType::Category, $slug, 'newest');
    }

    public function tags(Request $request): JsonResponse
    {
        return $this->index(PublicDirectoryType::Tag, 'name_asc', $request);
    }

    public function tag(string $slug): JsonResponse
    {
        return $this->show(PublicDirectoryType::Tag, $slug, 'newest');
    }

    private function index(PublicDirectoryType $type, string $defaultSort, Request $request): JsonResponse
    {
        $config = $this->listingConfig->forSite(SiteContext::get(), $type);
        $filter = $this->filterFactory->fromQueryParams($request->all(), $config, $defaultSort);

        return $this->resourceResponse([
            'data' => $this->directories->index($type, SiteContext::getId(), $filter),
        ]);
    }

    private function show(PublicDirectoryType $type, string $slug, string $defaultSort): JsonResponse
    {
        $config = $this->listingConfig->forSite(SiteContext::get(), $type);
        $filter = $this->filterFactory->fromQueryParams($_GET, $config, $defaultSort);

        $document = $this->directories->show($type, $slug, SiteContext::getId(), $filter);

        if (!$document) {
            return $this->errorResponse('Directory entity not found.', 404);
        }

        return $this->resourceResponse(['data' => $document]);
    }
}