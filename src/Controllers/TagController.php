<?php

namespace App\Controllers;

use App\Actions\Tag\BulkDeleteTag;
use App\Actions\Tag\CloneTag;
use App\Actions\Tag\MergeTag;
use App\Exceptions\CannotDeleteException;
use App\Framework\Container;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\SiteContext;
use App\Framework\Validation\Validator;
use App\Models\Tag;
use App\Repositories\Cms\TagRepository;
use App\Requests\BulkDeleteRequest;
use App\Requests\CreateTagRequest;
use App\Requests\UpdateTagRequest;
use App\Resources\TagResource;
use App\Search\SearchCriteriaParser;
use App\Services\Cms\TagService;
use Exception;

class TagController extends Controller
{
    private $tagRepository;
    private $validator;
    private TagService $tagService;

    public function __construct(TagRepository $tagRepository, Validator $validator, TagService $tagService)
    {
        $this->tagRepository = $tagRepository;
        $this->validator = $validator;
        $this->tagService = $tagService;
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);;
            $result = $this->tagRepository->search($criteria);

            $collection = new PaginatedResourceCollection($result, TagResource::class);
            return $this->resourceResponse($collection->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $tag = is_numeric($id)
                ? $this->tagRepository->find((int)$id)
                : $this->tagRepository->findBySlug($id);

            if (!$tag) {
                return $this->errorResponse('Tag not found', 404);
            }

            return $this->jsonResponse(['tag' => $tag->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreateTagRequest $request, string $siteName): JsonResponse
    {
        try {
            $tag = $this->tagRepository->create($request->validated());
            return $this->jsonResponse(['tag' => $tag->toArray()], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->getErrors());
        }
    }

    public function update(int $id, UpdateTagRequest $request, string $siteName): JsonResponse
    {
        try {
            $tag = $this->tagRepository->update($id, $request->validated());
            if (!$tag) {
                return $this->errorResponse('Tag not found', 404);
            }
            return $this->jsonResponse(['tag' => $tag->toArray()]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->getErrors());
        }
    }

    public function destroy(Request $request, int $id, string $siteName): JsonResponse
    {
        try {
            $reassignToId = $request->input('reassignId');
            $this->tagService->delete($id, $reassignToId);

            return $this->jsonResponse([
                'message' => 'Tag deleted successfully'
            ]);
        } catch (CannotDeleteException $e) {
            return $this->jsonResponse([
                'message' => $e->getMessage(),
                'pages_count' => $e->getRelatedCount(),
                'requires_reassignment' => true
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse([
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'message' => 'An error occurred while deleting the tag'
            ], 500);
        }
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q', '');
            $limit = min((int)$request->get('limit', 10), 50);

            $tags = $this->tagRepository->searchTags($query, $limit);
            return $this->jsonResponse(['tags' => $tags->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function popular(): JsonResponse
    {
        try {
            $tags = $this->tagRepository->getPopularTags(30);
            return $this->jsonResponse(['tags' => $tags->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function featured(): JsonResponse
    {
        try {
            $tags = $this->tagRepository->getFeaturedTags();

            return $this->jsonResponse(['tags' => $tags->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function cloud(): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();
            $tags = $this->tagRepository->getTagCloud(100, $siteId);

            return $this->jsonResponse(['tags' => $tags->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function cleanup(): JsonResponse
    {
        try {
            $deletedCount = $this->tagRepository->cleanupUnusedTags();
            return $this->successResponse("Cleaned up {$deletedCount} unused tags");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function merge(Request $request): JsonResponse
    {
        try {
            $fromTagId = $request->get('from_tag_id');
            $toTagId = $request->get('to_tag_id');

            $mergeTags = Container::getInstance()->make(MergeTag::class);

            $deletedCount = $mergeTags->handle($fromTagId, $toTagId);
            return $this->successResponse("merged successfully");
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function checkDelete(int $id, string $siteName): JsonResponse
    {
        try {
            $result = $this->tagService->checkDeletable($id);

            if ($result['requires_reassignment']) {
                $alternatives = $this->tagService->getAlternativeTags($id);
                $result['alternatives'] = $alternatives;
            }

            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'message' => 'Tag not found'
            ], 404);
        }
    }

    public function duplicate(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $newName = $data['name'] ?? null;

            $cloneTag = Container::getInstance()->make(CloneTag::class);

            $success = $cloneTag->handle($id, $newName);

            if ($success) {
                // Fetch the newly created tag
                $tag = Tag::where('name', 'LIKE', '%Copy%')
                    ->orderBy('id', 'desc')
                    ->first();

                return $this->jsonResponse($tag->toArray(), 201);
            }

            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to duplicate tag'
            ], 500);

        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'not found') !== false) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 404);
            }

            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to duplicate tag: ' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $bulkDelete = Container::getInstance()->make(BulkDeleteTag::class);

            $result = $bulkDelete->handle($data['ids']);

            return $this->resourceResponse([
                'message' => "Bulk delete completed. Deleted: " . count($result['deleted']) . ", Failed: " . count($result['failed']),
                'result' => $result
            ], 200);
        } catch (ValidationException $e) {
            return $this->resourceResponse(['error' => 'Validation failed', 'errors' => $e->getErrors()], 422);
        } catch (\Exception $e) {
            return $this->resourceResponse(['error' => 'Bulk delete failed: ' . $e->getMessage()], 500);
        }
    }
}