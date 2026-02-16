<?php

namespace App\Controllers\Cms;

use App\Actions\Author\BulkDeleteAuthor;
use App\Actions\Author\CloneAuthor;
use App\Actions\Author\MergeAuthor;
use App\Controllers\Controller;
use App\Framework\Container;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Models\Site;
use App\Repositories\Cms\AuthorRepository;
use App\Requests\BulkDeleteRequest;
use App\Requests\CreateAuthorRequest;
use App\Requests\UpdateAuthorRequest;
use App\Resources\AuthorResource;
use App\Search\SearchCriteriaParser;
use App\Services\Cms\AuthorService;
use Exception;

class AuthorController extends Controller
{
    private AuthorService $authorService;

    public function __construct(
        AuthorService                     $authorService,
        private readonly AuthorRepository $authorRepository
    )
    {
        $this->authorService = $authorService;
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $result = $this->authorRepository->search($criteria);

            $collection = new PaginatedResourceCollection($result, AuthorResource::class);

            return $this->resourceResponse($collection->toArray());

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreateAuthorRequest $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->validated();
            $siteId = Site::resolveSite($siteName);

            $avatarFile = $request->hasFile('avatar') ? $request->file('avatar') : null;

            $author = $this->authorService->createAuthor($data, $siteId, $avatarFile);

            return $this->jsonResponse(['author' => $author->toArray()], 201);

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
                $author = $this->authorService->getAuthorById((int)$id);
            } else {
                $author = $this->authorService->getAuthorBySlug($id);
            }

            if (!$author) {
                return $this->errorResponse('Author not found', 404);
            }

            $counts = $author->getCounts();
            $data = array_merge($counts, $author->toArray());

            if ($author->relationLoaded('pages')) {
                $data['pages'] = $author->pages->toArray();
            }

            return $this->jsonResponse(['author' => $data]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, UpdateAuthorRequest $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->validated();
            $avatarFile = $request->hasFile('avatar') ? $request->file('avatar') : null;

            $author = $this->authorService->updateAuthor($id, $data, $avatarFile);

            return $this->jsonResponse(['author' => $author->toArray()]);

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

    public function destroy(int $id, Request $request, string $siteName): JsonResponse
    {
        try {
            $result = $this->authorService->delete($id, $request->get('reassignId'));

            if (!$result) {
                return $this->errorResponse('Author not found', 404);
            }

            return $this->successResponse('Author deleted successfully');

        } catch (Exception $e) {
            return $this->errorResponse('Author not found', 404);
        }
    }

    public function getActive(): JsonResponse
    {
        try {
            $authors = $this->authorService->getActiveAuthors();
            return $this->jsonResponse(['authors' => $authors]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function merge(Request $request, string $siteName): JsonResponse
    {
        try {
            $sourceId = $request->get('source_author_id');
            $targetId = $request->get('target_author_id');

            if (!$sourceId || !$targetId) {
                return $this->errorResponse('Both source and target author IDs are required', 400);
            }

            $mergeAuthor = Container::getInstance()->make(MergeAuthor::class);

            $result = $mergeAuthor->handle((int)$sourceId, (int)$targetId);

            return $this->successResponse('Authors merged successfully');

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function checkDelete(int $id, string $siteName): JsonResponse
    {
        try {
            $result = $this->authorService->checkDeletable($id);

            if ($result['requires_reassignment']) {
                $alternatives = $this->authorService->getAlternativeAuthors($id);
                $result['alternatives'] = $alternatives;
            }

            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'message' => 'Author not found'
            ], 404);
        }
    }

    public function duplicate(int $id, Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $newName = $data['name'] ?? null;

            $cloneAuthor = Container::getInstance()->make(CloneAuthor::class);

            $results = $cloneAuthor->handle($id, $newName);

            return $this->jsonResponse($results, 201);

        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'not found') !== false) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 404);
            }

            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to duplicate author: ' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $bulkDeleteAuthor = Container::getInstance()->make(BulkDeleteAuthor::class);

            $result = $bulkDeleteAuthor->handle($data['ids']);

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