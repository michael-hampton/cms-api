<?php

namespace App\Controllers;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Parsers\BlockRegistry;
use App\Repositories\PageRepository;
use App\Resources\PageResource;
use App\Search\PaginatedResult;
use App\Search\SearchCriteriaParser;
use App\Services\PageService;
use Exception;

class PageController extends Controller
{
    private $pageService;
    private $blockRegistry;

    public function __construct(PageService $pageService, BlockRegistry $blockRegistry, private PageRepository $pageRepository)
    {
        $this->pageService = $pageService;
        $this->blockRegistry = $blockRegistry;

        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request);
            $result = $this->pageRepository->search($criteria);

            // Format blocks in the paginated data
            $formattedData = $result->getData();
            $formattedData = array_map(function($page) {
                if (!empty($page['blocks'])) {
                    $page['blocks'] = array_map(function($block) {
                        return [
                            'data' => [...json_decode($block['data'], true), 'type' => $block['type']],
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

    public function store(Request $request): JsonResponse
    {
        try {
            $requestData = $request->all();

            // Determine if this is an update or create based on presence of ID
            if (!empty($requestData['id'])) {
                $page = $this->pageService->updatePageWithAllData($requestData['id'], $requestData);
                $statusCode = 200;
            } else {
                $page = $this->pageService->createPageWithAllData($requestData);
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

    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $requestData = $request->all();
            $page = $this->pageService->updatePageWithAllData($id, $requestData);

            return $this->jsonResponse(['page' => $page->toArray()]);

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

    public function destroy(int $id): JsonResponse
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
            $category = $request->get('category', '');
            $tag = $request->get('tag', '');
            $status = $request->get('status', 'published');

            $pages = $this->pageService->searchPages($query, $category, $tag, $status);

            return $this->jsonResponse(['pages' => $pages]);
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

    public function bulkUpdate(Request $request): JsonResponse
    {
        try {
            $pageIds = $request->get('page_ids', []);
            $updateData = $request->get('data', []);

            $results = $this->pageService->bulkUpdatePages($pageIds, $updateData);

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

    public function duplicate(int $id): JsonResponse
    {
        try {
            $newPage = $this->pageService->duplicatePage($id);

            if (!$newPage) {
                return $this->errorResponse('Page not found', 404);
            }

            return $this->jsonResponse(['page' => $newPage->toArrayWithRelations()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}