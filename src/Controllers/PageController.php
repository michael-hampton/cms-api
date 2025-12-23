<?php

namespace App\Controllers;

use App\Actions\BulkApprovePages;
use App\Actions\BulkDeletePages;
use App\Actions\BulkUpdatePage;
use App\Actions\BulkUpdatePageStatus;
use App\Actions\ClonePage;
use App\Actions\ClonePageToSite;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Container;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\SiteContext;
use App\Parsers\BlockRegistry;
use App\Repositories\AuthorRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\PageRepository;
use App\Repositories\TagRepository;
use App\Resources\PageResource;
use App\Search\SearchCriteriaParser;
use App\Services\PageService;
use Exception;

class PageController extends Controller
{
    public function __construct(
        private readonly PageService        $pageService,
        private readonly BlockRegistry      $blockRegistry,
        private readonly PageRepository     $pageRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly TagRepository      $tagRepository,
        private readonly AuthorRepository   $authorRepository,
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $result = $this->pageRepository->search($criteria);
            // Format blocks in the paginated data
            $formattedData = $result->getData();

            $formattedData = array_map(function($page) {
                if (!empty($page['blocks'])) {
                    $page['blocks'] = array_map(function($block) {
                        return [
                            'data' => [...$block['data'], 'type' => $block['type']],
                            'type' => $block['type'],
                            'id' => $block['id'],
                            'order' => $block['order'],
                        ];
                    }, $page['blocks']);
                }
                return $page;
            }, $formattedData);

            $result->setData($formattedData);

            $collection = new PaginatedResourceCollection($result, PageResource::class);

            return $this->resourceResponse($collection->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(Request $request, string $siteName): JsonResponse
    {
        try {
            $requestData = $request->all();

            // Determine if this is an update or create based on presence of ID
            if (!empty($requestData['id'])) {
                $page = $this->pageService->updatePageWithAllData($requestData['id'], $requestData, $request->get('site_id'));
                $statusCode = 200;
            } else {
                $page = $this->pageService->createPageWithAllData($requestData, $request->get('site_id'));
                $statusCode = 201;
            }

            return $this->jsonResponse(['page' => $page->toArray()], $statusCode);

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

    public function show($id): JsonResponse
    {
        try {
            if (is_numeric($id)) {
                $pageData = $this->pageService->getCompletePageData((int)$id);
            } else {
                // Handle slug-based lookup
                $pageData = $this->pageService->findPageBySlug($id);
            }

            if (!$pageData) {
                return $this->errorResponse('Page not found', 404);
            }

            return $this->jsonResponse($pageData->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $requestData = $request->all();
            $page = $this->pageService->updatePageWithAllData($id, $requestData, $request->get('site_id'));

            return $this->jsonResponse(['page' => $page->toArray()]);

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

    public function destroy(int $id, string $siteName): JsonResponse
    {
        try {
            $result = $this->pageService->deletePage($id);

            if (!$result) {
                return $this->errorResponse('Page not found', 404);
            }

            return $this->successResponse('Page deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getAvailableBlockTypes(): JsonResponse
    {
        try {
            $types = [];
            foreach ($this->blockRegistry->getAllParsers() as $type => $parser) {
                $types[] = [
                    'type' => $type,
                    'validation_rules' => array_keys($parser->getValidationRules())
                ];
            }

            return $this->jsonResponse(['block_types' => $types]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getFeaturedPages(): JsonResponse
    {
        try {
            $pages = $this->pageService->getFeaturedPages();
            return $this->jsonResponse(['pages' => $pages]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function searchPages(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q', '');
            $pageType = $request->get('page_type', '');
            $category = $request->get('category', '');
            $tag = $request->get('tag', '');
            $status = $request->get('status', 'published');
            $limit = $request->get('limit', 50);
            $offset = $request->get('offset', 0);
            $siteId = $request->get('site_id') ?? SiteContext::getId();

            $criteria = SearchCriteriaParser::fromRequest($request, $request->get('site_name'));

            // Add search query
            if (!empty($query)) {
                $criteria->setSearchQuery($query);
            }

            // Add filters
            if (!empty($pageType)) {
                $criteria->addFilter('page_type', $pageType);
            }
            if (!empty($category)) {
                $criteria->addFilter('category_id', $category);
            }
            if (!empty($tag)) {
                $criteria->addFilter('tag_id', $tag);
            }
            $criteria->addFilter('status', $status);

            // Set pagination
            $criteria->setPerPage($limit);
            $criteria->setPage(floor($offset / $limit) + 1);

            $result = $this->pageRepository->search($criteria);

            // Get current member for access control
            $accessService = Container::getInstance()->make(\App\Services\ArticleAccessService::class);

            // Enrich pages with access information
            $enrichedPages = $accessService->enrichPagesWithAccessInfo(
                $result->getData(),
                MemberAuth::getMember(),
                $siteId
            );

            // Format results with images and time ago
            $formattedData = array_map(function ($page) {
                // Image resolution logic
                $imageUrl = '';
                $cropOverrides = $page['crop_overrides'] ?? null;
                $resolvedImages = $page['resolved_images'] ?? null;
                $useAsHero = ($page['listing_use_as_hero'] ?? false);

                if ($useAsHero) {
                    if (isset($cropOverrides['hero-banner']['imageUrl'])) {
                        $imageUrl = $cropOverrides['hero-banner']['imageUrl'];
                    } elseif (isset($resolvedImages['hero-banner']['image_url'])) {
                        $imageUrl = $resolvedImages['hero-banner']['image_url'];
                    }
                } else {
                    if (isset($cropOverrides['listing-card']['imageUrl'])) {
                        $imageUrl = $cropOverrides['listing-card']['imageUrl'];
                    } elseif (isset($resolvedImages['listing-card']['image_url'])) {
                        $imageUrl = $resolvedImages['listing-card']['image_url'];
                    }
                }

                $page['image_url'] = $imageUrl;

                // Calculate time ago
                $publishedAt = $page['published_at'] ?? null;
                if ($publishedAt) {
                    $page['time_ago'] = getTimeAgo($publishedAt);
                }

                // Access information is already included from enrichPagesWithAccessInfo
                // It includes: access_level, can_view, denial_reason, access_reason

                return $page;
            }, $enrichedPages);

            // Get all categories for filters
            $allCategories = $this->categoryRepository->getBySiteId($siteId);
            $allAuthors = $this->authorRepository->getBySiteId($siteId);
            $allTags = $this->tagRepository->getBySiteId($siteId);

            return $this->resourceResponse([
                'results' => $formattedData,
                'total' => $result->getTotal(),
                'query' => $query,
                'filters' => [
                    'page_type' => $pageType,
                    'category' => $category,
                    'tag' => $tag
                ],
                'categories' => array_values($allCategories),
                'authors' => array_values($allAuthors),
                'tags' => array_values($allTags),
                'has_more' => ($offset + $limit) < $result->getTotal()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getPagesByCategory(string $category): JsonResponse
    {
        try {
            $pages = $this->pageService->getPagesByCategory($category);
            return $this->jsonResponse(['pages' => $pages]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getPagesByTag(string $tag): JsonResponse
    {
        try {
            $pages = $this->pageService->getPagesByTag($tag);
            return $this->jsonResponse(['pages' => $pages]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function bulkUpdate(Request $request, string $siteName): JsonResponse
    {
        try {
            $pageIds = $request->get('page_ids', []);
            $updateData = $request->get('data', []);

            $handler = Container::getInstance()->make(BulkUpdatePage::class);

            $results = $handler->handle($pageIds, $updateData, $request->get('site_id'));;

            return $this->jsonResponse(['results' => $results]);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getValidationResult()->getErrors()
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function duplicate(int $id, string $siteName): JsonResponse
    {
        try {
            $handler = Container::getInstance()->make(ClonePage::class);

            $results = $handler->handle($id);

            if (!$results) {
                return $this->errorResponse('Page not found', 404);
            }

            return $this->jsonResponse($results, 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function cloneToSite(int $id, string $siteName, Request $request): JsonResponse
    {
        try {
            $targetSiteId = $request->get('target_site_id');

            if (!$targetSiteId) {
                return $this->errorResponse('target_site_id is required', 422);
            }

            $newTitle = $request->get('title', null);

            $handler = Container::getInstance()->make(ClonePageToSite::class);

            $results = $handler->handle($id, $targetSiteId, $newTitle);

            if (!$results) {
                return $this->errorResponse('Page not found', 404);
            }

            return $this->jsonResponse($results, 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function bulkDelete(Request $request, string $siteName): JsonResponse
    {
        try {
            $ids = $request->get('ids', []);

            if (empty($ids)) {
                return $this->errorResponse('No page IDs provided', 422);
            }

            $handler = Container::getInstance()->make(BulkDeletePages::class);

            $results = $handler->handle($ids);

            return $this->jsonResponse([
                'message' => 'Pages deleted successfully',
                'deleted' => $results
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function bulkUpdateStatus(Request $request, string $siteName): JsonResponse
    {
        try {
            $ids = $request->get('ids', []);
            $status = $request->get('status');

            if (empty($ids)) {
                return $this->errorResponse('No page IDs provided', 422);
            }

            if (empty($status)) {
                return $this->errorResponse('Status is required', 422);
            }

            $handler = Container::getInstance()->make(BulkUpdatePageStatus::class);

            $results = $handler->handle($ids, $status);

            return $this->jsonResponse([
                'message' => 'Pages updated successfully',
                'updated' => $results
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function approve(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $userId = $request->get('user_id'); // Get from authenticated user

            if (!$userId) {
                return $this->errorResponse('User ID required', 422);
            }

            $page = $this->pageService->approvePage($id, $userId);

            return $this->jsonResponse(['page' => $page->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function reject(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $userId = $request->get('user_id');
            $reason = $request->get('reason');

            if (!$userId) {
                return $this->errorResponse('User ID required', 422);
            }

            $page = $this->pageService->rejectPage($id, $userId, $reason);

            return $this->jsonResponse(['page' => $page->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function putOnHold(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $userId = $request->get('user_id');
            $reason = $request->get('reason');

            if (!$userId) {
                return $this->errorResponse('User ID required', 422);
            }

            $page = $this->pageService->putPageOnHold($id, $userId, $reason);

            return $this->jsonResponse(['page' => $page->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function makePrivate(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $userId = $request->get('user_id');

            if (!$userId) {
                return $this->errorResponse('User ID required', 422);
            }

            $page = $this->pageService->makePagePrivate($id, $userId);

            return $this->jsonResponse(['page' => $page->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function bulkApprove(Request $request, string $siteName): JsonResponse
    {
        try {
            $ids = $request->get('ids', []);
            $userId = $request->get('user_id');

            if (empty($ids)) {
                return $this->errorResponse('No page IDs provided', 422);
            }

            if (!$userId) {
                return $this->errorResponse('User ID required', 422);
            }

            $handler = Container::getInstance()->make(BulkApprovePages::class);

            $results = $handler->handle($ids, $userId);

            return $this->jsonResponse([
                'message' => 'Pages processed for approval',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function makeInternal(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $userId = $request->get('user_id');

            if (!$userId) {
                return $this->errorResponse('User ID required', 422);
            }

            $page = $this->pageService->makePageInternal($id, $userId);

            return $this->jsonResponse(['page' => $page->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

}