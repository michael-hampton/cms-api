<?php

namespace App\Controllers\Cms;

use App\Actions\Pages\BulkAddContributorsToPages;
use App\Actions\Pages\BulkAddTagsToPages;
use App\Actions\Pages\BulkApprovePages;
use App\Actions\Pages\BulkChangePageAuthors;
use App\Actions\Pages\BulkClonePages;
use App\Actions\Pages\BulkDeletePages;
use App\Actions\Pages\BulkRemoveContributorsFromPages;
use App\Actions\Pages\BulkRemoveTagsFromPages;
use App\Actions\Pages\BulkSchedulePages;
use App\Actions\Pages\BulkUpdatePage;
use App\Actions\Pages\BulkUpdatePageRegions;
use App\Actions\Pages\BulkUpdatePageStatus;
use App\Actions\Pages\ClonePage;
use App\Actions\Pages\ClonePageToSite;
use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Container;
use App\Framework\Exceptions\UnauthorizedException;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\SiteContext;
use App\Parsers\BlockRegistry;
use App\Repositories\Cms\AuthorRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\Pages\PageCollaboratorRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Requests\StorePageRequest;
use App\Requests\UpdatePageRequest;
use App\Resources\PageResource;
use App\Search\SearchCriteriaParser;
use App\Services\Cms\ContentWorkflowAuthorizationService;
use App\Services\Cms\Pages\PageService;
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
        private readonly PageCollaboratorRepository $collaboratorRepository,
        private readonly ContentWorkflowAuthorizationService $workflowAuthorization,
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $site): JsonResponse
    {
        try {
            $userId = Auth::id();
            $criteria = SearchCriteriaParser::fromRequest($request, $site);
            $criteria->addFilter('exclude_private_internal', $userId); //hide private and internal from search
            $result = $this->pageRepository->search($criteria);
            // Format blocks in the paginated data
            $formattedData = $result->getData();

            $formattedData = array_map(function ($page) {
                if (!empty($page['blocks'])) {
                    $page['blocks'] = array_map(function ($block) {
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

    public function store(StorePageRequest $request, string $site): JsonResponse
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

    public function update(int $id, UpdatePageRequest $request, string $site): JsonResponse
    {
        try {
            $requestData = $request->all();
            $siteId = SiteContext::getId();

            //check if page exists before trying to update it
            $page = $this->pageRepository->find($id);

            if (empty($page)) {
                $page = $this->pageService->createPageWithAllData($requestData, $siteId);
                return $this->jsonResponse(['page' => $page->toArray()]);
            }

            $page = $this->pageService->updatePageWithAllData($id, $requestData, $request->get('site_id'), $page);

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

    public function destroy(int $id, string $site): JsonResponse
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
            $accessService = Container::getInstance()->make(\App\Services\Cms\Pages\ArticleAccessService::class);

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

    public function bulkUpdate(Request $request, string $site): JsonResponse
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

    public function duplicate(int $id, string $site): JsonResponse
    {
        try {
            /** @var ClonePage $handler */
            $handler = Container::getInstance()->make(ClonePage::class);
            $userId = Auth::id();

            $results = $handler->handle($id, [], $userId);

            if (!$results) {
                return $this->errorResponse('Page not found', 404);
            }

            return $this->jsonResponse($results, 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function cloneToSite(int $id, string $site, Request $request): JsonResponse
    {
        try {
            $targetSiteId = $request->get('target_site_id');
            $userId = Auth::id();

            if (!$targetSiteId) {
                return $this->errorResponse('target_site_id is required', 422);
            }

            $newTitle = $request->get('title', null);

            /** @var ClonePageToSite $handler */
            $handler = Container::getInstance()->make(ClonePageToSite::class);

            $results = $handler->handle($id, $targetSiteId, $newTitle, [], $userId);

            if (!$results) {
                return $this->errorResponse('Page not found', 404);
            }

            return $this->jsonResponse($results, 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function bulkDelete(Request $request, string $site): JsonResponse
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

    public function bulkUpdateStatus(Request $request, string $site): JsonResponse
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

    public function approve(int $id, Request $request, string $site): JsonResponse
    {
        try {
            $userId = $request->get('user_id'); // Get from authenticated user

            if (!$userId) {
                return $this->errorResponse('User ID required', 422);
            }

            $this->workflowAuthorization->assertCanApprove((int) $userId, SiteContext::getId(), 'pages');

            $page = $this->pageService->approvePage($id, $userId);

            return $this->jsonResponse(['page' => $page->toArray()]);
        } catch (UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function reject(int $id, Request $request, string $site): JsonResponse
    {
        try {
            $userId = $request->get('user_id');
            $reason = $request->get('reason');

            if (!$userId) {
                return $this->errorResponse('User ID required', 422);
            }

            $this->workflowAuthorization->assertCanReject((int) $userId, SiteContext::getId(), 'pages');

            $page = $this->pageService->rejectPage($id, $userId, $reason);

            return $this->jsonResponse(['page' => $page->toArray()]);
        } catch (UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function putOnHold(int $id, Request $request, string $site): JsonResponse
    {
        try {
            $userId = $request->get('user_id');
            $reason = $request->get('reason');

            if (!$userId) {
                return $this->errorResponse('User ID required', 422);
            }

            $this->workflowAuthorization->assertCanHold((int) $userId, SiteContext::getId(), 'pages');

            $page = $this->pageService->putPageOnHold($id, $userId, $reason);

            return $this->jsonResponse(['page' => $page->toArray()]);
        } catch (UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function makePrivate(int $id, Request $request, string $site): JsonResponse
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

    public function bulkApprove(Request $request, string $site): JsonResponse
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

    public function makeInternal(int $id, Request $request, string $site): JsonResponse
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

    public function getCalendarPages(Request $request, string $site): JsonResponse
    {
        try {
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $excludeStatus = $request->get('exclude_status');
            $authors = $request->get('authors');
            $types = $request->get('types');
            $sites = $request->get('sites');
            $tags = $request->get('tags');
            $categories = $request->get('categories');
            $search = $request->get('search');

            if (!$startDate || !$endDate) {
                return $this->errorResponse('start_date and end_date are required', 422);
            }

            $criteria = SearchCriteriaParser::fromRequest($request, $site);

            if (!empty($search)) {
                $criteria->setSearchQuery($search);
            }

            // Date range filter for published_at or scheduled_at
            $criteria->addFilter('date_range', [
                'start' => $startDate,
                'end' => $endDate
            ]);

            // Status filter
            if ($excludeStatus === 'scheduled') {
                $criteria->addFilter('status', 'published');
            } elseif ($excludeStatus === 'published') {
                $criteria->addFilter('status', 'scheduled');
            } else {
                // Include both scheduled and published
                $criteria->addFilter('status_in', ['scheduled', 'published']);
            }

            // Author filter
            if (!empty($authors)) {
                $authorIds = is_array($authors) ? $authors : explode(',', $authors);
                $criteria->addFilter('author_ids', $authorIds);
            }

            // Type filter
            if (!empty($types)) {
                $typeList = is_array($types) ? $types : explode(',', $types);
                $criteria->addFilter('page_types', $typeList);
            }

            // Site filter
            if (!empty($sites)) {
                $siteIds = is_array($sites) ? $sites : explode(',', $sites);
                $criteria->addFilter('site_ids', $siteIds);
            }

            // Tag filter
            if (!empty($tags)) {
                $tagIds = is_array($tags) ? $tags : explode(',', $tags);
                $criteria->addFilter('tag_ids', $tagIds);
            }

            // Category filter
            if (!empty($categories)) {
                $categoryIds = is_array($categories) ? $categories : explode(',', $categories);
                $criteria->addFilter('category_ids', $categoryIds);
            }

            // Get all results (no pagination for calendar)
            $criteria->setPerPage(1000);

            $result = $this->pageRepository->searchCalendarPages($criteria);

            // Format results
            $formattedPages = array_map(function ($page) {
                // Format categories
                $categories = [];
                if (isset($page['categories'])) {
                    $categories = array_map(function ($cat) {
                        return [
                            'id' => $cat['id'],
                            'name' => $cat['name'],
                            'color' => $cat['color'] ?? null
                        ];
                    }, $page['categories']);
                }

                // Format tags
                $tags = [];
                if (isset($page['tags'])) {
                    $tags = array_map(function ($tag) {
                        return [
                            'id' => $tag['id'],
                            'name' => $tag['name'],
                            'color' => $tag['color'] ?? null
                        ];
                    }, $page['tags']);
                }

                // Format authors
                $authors = [];
                if (isset($page['pageAuthors'])) {
                    $authors = array_map(function ($pageAuthor) {
                        return [
                            'id' => $pageAuthor['author']['id'] ?? null,
                            'name' => $pageAuthor['author']['name'] ?? null,
                            'role' => $pageAuthor['role'] ?? null,
                            'avatar' => $pageAuthor['author']['avatar'] ?? null
                        ];
                    }, $page['pageAuthors']);
                }

                // Format created by user
                $createdBy = null;
                if (!empty($page['created_by'])) {
                    // You'll need to load the user relationship in the repository
                    // For now, we'll use the creator relationship if it exists
                    $createdBy = [
                        'id' => $page['created_by'],
                        'name' => $page['creator']['name'] ?? 'Unknown',
                        'avatar' => $page['creator']['avatar'] ?? null
                    ];
                }

                return [
                    'id' => $page['id'],
                    'title' => $page['title'],
                    'status' => $page['status'],
                    'published_at' => $page['published_at']?->format('Y-m-d H:i:s'),
                    'scheduled_at' => $page['scheduled_at'] ?? null,
                    'page_type' => $page['page_type'],
                    'authors' => $authors,
                    'categories' => $categories,
                    'tags' => $tags,
                    'created_by' => $createdBy,
                    'site' => [
                        'id' => $page['site_id'] ?? null,
                        'name' => $page['site']['name'] ?? null,
                        'slug' => $page['site']['slug'] ?? null
                    ]
                ];
            }, $result->getData());

            return $this->resourceResponse([
                'success' => true,
                'items' => $formattedPages,
                'total' => count($formattedPages)
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function bulkAddTags(Request $request): JsonResponse
    {
        $pageIds = $request->input('page_ids', []);
        $tagIds = $request->input('tag_ids', []);
        $siteId = SiteContext::getId();

        $action = Container::getInstance()->make(BulkAddTagsToPages::class);

        $results = $action->handle($pageIds, $tagIds, $siteId);

        return $this->resourceResponse([
            'success' => true,
            'data' => ['results' => $results]
        ]);
    }

    public function bulkRemoveTags(Request $request): JsonResponse
    {
        $pageIds = $request->input('page_ids', []);
        $tagIds = $request->input('tag_ids', []);
        $siteId = SiteContext::getId();

        $action = Container::getInstance()->make(BulkRemoveTagsFromPages::class);

        $results = $action->handle($pageIds, $tagIds, $siteId);

        return $this->resourceResponse([
            'success' => true,
            'data' => ['results' => $results]
        ]);
    }

    public function bulkChangeAuthor(Request $request): JsonResponse
    {
        $pageIds = $request->input('page_ids', []);
        $authorId = $request->input('author_id');
        $siteId = SiteContext::getId();

        $action = Container::getInstance()->make(BulkChangePageAuthors::class);

        $results = $action->handle($pageIds, $authorId, $siteId);

        return $this->resourceResponse([
            'success' => true,
            'data' => ['results' => $results]
        ]);
    }

    public function bulkAddContributors(Request $request): JsonResponse
    {
        $pageIds = $request->input('page_ids', []);
        $contributorIds = $request->input('contributor_ids', []);
        $siteId = SiteContext::getId();

        $action = Container::getInstance()->make(BulkAddContributorsToPages::class);

        $results = $action->handle($pageIds, $contributorIds, $siteId);

        return $this->jsonResponse([
            'success' => true,
            'data' => ['results' => $results]
        ]);
    }

    public function bulkRemoveContributors(Request $request): JsonResponse
    {
        $pageIds = $request->input('page_ids', []);
        $contributorIds = $request->input('contributor_ids', []);
        $siteId = SiteContext::getId();

        $action = Container::getInstance()->make(BulkRemoveContributorsFromPages::class);

        $results = $action->handle($pageIds, $contributorIds, $siteId);

        return $this->jsonResponse([
            'success' => true,
            'data' => ['results' => $results]
        ]);
    }

    public function bulkUpdateRegions(Request $request): JsonResponse
    {
        $pageIds = $request->input('page_ids', []);
        $regionSetIds = $request->input('region_set_ids', []);
        $territoryIds = $request->input('territory_ids', []);
        $siteId = SiteContext::getId();

        $action = Container::getInstance()->make(BulkUpdatePageRegions::class);

        $results = $action->handle($pageIds, $regionSetIds, $territoryIds, $siteId);

        return $this->jsonResponse([
            'success' => true,
            'data' => ['results' => $results]
        ]);
    }

    public function bulkClone(Request $request): JsonResponse
    {
        $pageIds = $request->input('page_ids', []);
        $options = [
            'withPrefix' => $request->input('with_prefix', true),
            'asDraft' => $request->input('as_draft', true),
        ];

        $action = Container::getInstance()->make(BulkClonePages::class);

        $results = $action->handle($pageIds, $options);

        return $this->resourceResponse([
            'success' => true,
            'data' => ['results' => $results]
        ]);
    }

    public function bulkSchedule(Request $request, string $site): JsonResponse
    {
        try {
            $schedules = $request->get('schedules', []);

            if (empty($schedules)) {
                return $this->errorResponse('No schedules provided', 422);
            }

            // Validate schedule format
            foreach ($schedules as $schedule) {
                if (!isset($schedule['page_id']) || !isset($schedule['scheduled_date'])) {
                    return $this->errorResponse('Invalid schedule format. Each schedule must have page_id and scheduled_date', 422);
                }
            }

            $handler = Container::getInstance()->make(BulkSchedulePages::class);
            $results = $handler->handle($schedules);

            return $this->jsonResponse([
                'message' => 'Pages scheduled successfully',
                'results' => $results
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

//    public function bulkExport(Request $request): JsonResponse
//    {
//        $pageIds = $request->input('page_ids', []);
//        $format = $request->input('format', 'json');
//        $includeBlocks = $request->input('include_blocks', true);
//
//        $action = new BulkExportPages(
//            $this->pageRepository,
//            $this->blockRepository
//        );

//        $content = $action->handle($pageIds, $format, $includeBlocks);
//
//        $filename = 'pages-export.' . $format;
//        $mimeType = $format === 'csv' ? 'text/csv' : 'application/json';

//        return new Response($content, 200, [
//            'Content-Type' => $mimeType,
//            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
//        ]);
//    }

    public function updateSchedule(int $id, Request $request, string $site): JsonResponse
    {
        try {
            $scheduledDate = $request->get('scheduled_date');

            if (!$scheduledDate) {
                return $this->errorResponse('scheduled_date is required', 422);
            }

            $page = $this->pageRepository->find($id);

            if (!$page) {
                return $this->errorResponse('Page not found', 404);
            }

            // Update page with new scheduled date
            $updatedPage = $this->pageService->updatePageSchedule($id, $scheduledDate);

            return $this->jsonResponse([
                'page' => $updatedPage->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function unpublish(int $id, Request $request, string $site): JsonResponse
    {
        try {
            $userId = $request->get('user_id');
            $redirectUrl = $request->get('redirect_url');
            $skipRedirect = $request->get('skip_redirect', false);

            if (!$userId) {
                return $this->errorResponse('User ID required', 422);
            }

            $finalRedirectUrl = $skipRedirect ? null : $redirectUrl;

            $page = $this->pageService->unpublishPage($id, $userId, $finalRedirectUrl);

            return $this->jsonResponse(['page' => $page->toArray()]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function getCollaborators(string $site, int $pageId): JsonResponse
    {
        try {
            // Verify user has access to this page
            $page = $this->pageRepository->find($pageId);
            if (!$page) {
                return $this->errorResponse('Page not found', 404);
            }

            $collaborators = $this->collaboratorRepository->getForPage($pageId);

            return $this->resourceResponse([
                'success' => true,
                'data' => $collaborators
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getBrief(string $site, int $pageId): JsonResponse
    {
        try {
            $page = $this->pageRepository->find($pageId);
            if (!$page) {
                return $this->errorResponse('Page not found', 404);
            }

            $brief = $this->pageRepository->getBrief($pageId);

            if (!$brief) {
                return $this->resourceResponse([
                    'success' => true,
                    'data' => null,
                    'message' => 'This page was not created from a brief'
                ]);
            }

            return $this->resourceResponse([
                'success' => true,
                'data' => $brief->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function addCollaborator(Request $request, int $pageId): JsonResponse
    {
        try {
            $data = $request->all();

            if (!isset($data['user_id'])) {
                return $this->errorResponse('User ID is required', 400);
            }

            $role = $data['role'] ?? 'editor';
            $allowed_roles = ['viewer', 'editor', 'admin'];

            if (!in_array($role, $allowed_roles)) {
                return $this->errorResponse('Invalid role', 400);
            }

            $success = $this->collaboratorRepository->createForPage(
                $pageId,
                ['user_id' => (int)$data['user_id'], 'role' => $role],
            );

            if (!$success) {
                return $this->errorResponse('Collaborator already exists', 409);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Collaborator added successfully'
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function removeCollaborator(string $site, int $pageId, int $collaboratorId): JsonResponse
    {
        try {
            $success = $this->collaboratorRepository->remove($collaboratorId);

            return $this->resourceResponse([
                'success' => $success,
                'message' => $success ? 'Collaborator removed' : 'Failed to remove collaborator'
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function approveWithDecision(Request $request, string $site, int $id): JsonResponse
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                throw new UnauthorizedException('Unauthenticated.');
            }

            $decision = [
                'monetisation_decision' => $request->get('monetisation_decision', 'free'),
                'approved_price' => $request->get('approved_price'),
                'premium_note' => $request->get('premium_note'),
                'premium_rejection_reason' => $request->get('premium_rejection_reason'),
            ];

            if (!in_array($decision['monetisation_decision'], ['free', 'premium', 'reject_premium'], true)) {
                return $this->errorResponse('Invalid monetisation decision.', 422);
            }

            if ($decision['monetisation_decision'] === 'premium' && (int) $decision['approved_price'] <= 0) {
                return $this->errorResponse('Approved price is required for premium approval.', 422);
            }

            if ($decision['monetisation_decision'] === 'reject_premium' && empty(trim((string) $decision['premium_rejection_reason']))) {
                return $this->errorResponse('Premium rejection reason is required.', 422);
            }

            $page = $this->pageService->approvePageWithMonetisationDecision($id, $userId, $decision);

            return $this->jsonResponse(['page' => $page->toArray()]);
        } catch (UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), 401);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function premiumPriceRecommendation(string $site, int $id): JsonResponse
    {
        try {
            $page = $this->pageRepository->getCompletePageData($id);

            if (!$page) {
                return $this->errorResponse('Page not found.', 404);
            }

            $service = Container::getInstance()->make(
                \App\Services\Cms\Pages\PremiumPagePricingRecommendationService::class
            );

            $recommendation = $service->recommend($page);

            return $this->jsonResponse([
                'recommendation' => $recommendation->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

}
