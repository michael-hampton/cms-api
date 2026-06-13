<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePermissions;
use App\DTO\OpenCollab\ImageEvidenceData;
use App\DTO\OpenCollab\ImageSearchQuery;
use App\DTO\OpenCollab\ImageUploadData;
use App\Enums\OpenCollab\OpenCollabImageRights;
use App\Exceptions\OpenCollab\ImageLibraryAccessDeniedException;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Resource\ResourceCollection;
use App\Framework\Support\SiteContext;
use App\Repositories\Cms\SiteRepository;
use App\Requests\OpenCollab\SearchImagesRequest;
use App\Requests\OpenCollab\UploadImageRequest;
use App\Resources\BrandResource;
use App\Resources\NewsletterBrandingConfigurationResource;
use App\Resources\OpenCollab\ActivityEventResource;
use App\Resources\OpenCollab\ImageLibraryResource;
use App\Resources\PageResource;
use App\Services\OpenCollab\ImageLibraryService;
use App\Services\OpenCollab\Policies\ContributorImagePolicyInterface;

/**
 * Routes:
 *   GET  /api/{site}/open-collab/images
 *   GET  /api/{site}/open-collab/images/{imageId}
 *   POST /api/{site}/open-collab/images
 */
class ImageLibraryController extends Controller
{
    use AuthorizesSitePermissions;

    public function __construct(
        private readonly ImageLibraryService             $libraryService,
        private readonly ContributorImagePolicyInterface $imagePolicy,
        private readonly SiteRepository                  $siteRepository,
    ) {
        parent::__construct();
    }

    /**
     * GET /api/{site}/open-collab/images
     */
    public function index(SearchImagesRequest $request): JsonResponse
    {
        $site = $this->siteRepository->find(SiteContext::getId());
        if (!$site) {
            return $this->errorResponse('Site not found.', 404);
        }

        $data = $request->validated();

        $imageRights = null;
        if (!empty($data['image_rights'])) {
            try {
                $imageRights = OpenCollabImageRights::from($data['image_rights']);
            } catch (\ValueError) {
                return $this->errorResponse('Invalid image_rights value.', 422);
            }
        }

        $query = new ImageSearchQuery(
            page:         (int) ($data['page'] ?? 1),
            perPage:      (int) ($data['per_page'] ?? 20),
            search:       $data['search'] ?? null,
            imageRights:  $imageRights,
            uploadedFrom: $data['uploaded_from'] ?? null,
            uploadedTo:   $data['uploaded_to'] ?? null,
            sort:         $data['sort'] ?? 'created_at',
            direction:    $data['direction'] ?? 'desc',
        );

        try {
            $result = $this->libraryService->search(Auth::id(), $site, $query);
        } catch (ImageLibraryAccessDeniedException) {
            return $this->errorResponse('You do not have permission to browse the image library.', 403);
        }

        $collection = new PaginatedResourceCollection($result, ImageLibraryResource::class);

        return $this->resourceResponse($collection->toArray());
    }

    /**
     * GET /api/{site}/open-collab/images/{imageId}
     */
    public function show(int $imageId): JsonResponse
    {
        $site = $this->siteRepository->find(SiteContext::getId());
        if (!$site) {
            return $this->errorResponse('Site not found.', 404);
        }

        try {
            $image = $this->libraryService->findForContributor(Auth::id(), $site, $imageId);
        } catch (ImageLibraryAccessDeniedException) {
            return $this->errorResponse('Access denied.', 403);
        }

        if ($image === null) {
            return $this->errorResponse('Image not found.', 404);
        }

        return $this->jsonResponse([
            'image' => ImageLibraryResource::make($image)->toArray()
        ], 201);
    }

    /**
     * POST /api/{site}/open-collab/images
     */
    public function store(UploadImageRequest $request): JsonResponse
    {
        $site = $this->siteRepository->find(SiteContext::getId());
        if (!$site) {
            return $this->errorResponse('Site not found.', 404);
        }

        $data = $request->validated();

        try {
            $imageRights = OpenCollabImageRights::from($data['image_rights']);
        } catch (\ValueError) {
            return $this->errorResponse('Invalid image_rights value.', 422, [
                'image_rights' => ['The selected image rights are not valid.'],
            ]);
        }

        $uploadData = new ImageUploadData(
            file:         $request->file('file'),
            name:         $data['name'],
            imageRights:  $imageRights,
            altText:      $data['alt_text'],
            credit:       $data['credit'] ?? '',
            sourceContext: 'open_collab_article_editor',
        );

        $evidenceData = new ImageEvidenceData(
            siteId: (int) $site->id,
            cmsImageId: 0,
            contributorUserId: Auth::id(),
            imageRights: $imageRights,
            nameSubmitted: $data['name'],
            altTextSubmitted: $data['alt_text'],
            creditSubmitted: $data['credit'] ?? '',
            rightsConfirmation: (bool) ($data['rights_confirmation'] ?? false),
            aiGenerated: (bool) ($data['ai_generated'] ?? false),
            containsMusic: (bool) ($data['contains_music'] ?? false),
            sponsoredContent: (bool) ($data['sponsored_content'] ?? false),
            affiliateContent: (bool) ($data['affiliate_content'] ?? false),
            unclearRights: (bool) ($data['unclear_rights'] ?? false),
            requestCorrelationId: $request->header('X-Request-ID'),
            ipAddress: $request->ip(),
            userAgent: $request->header('User-Agent'),
        );

        try {
            $image = $this->libraryService->upload(Auth::id(), $site, $uploadData, $evidenceData);
        } catch (ImageLibraryAccessDeniedException) {
            return $this->errorResponse('You do not have permission to upload images.', 403);
        } catch (\App\Framework\Exceptions\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->getErrors());
        }

        return $this->resourceResponse([
            'image' => ImageLibraryResource::make($image)->toArray()
        ], 201);
    }
}