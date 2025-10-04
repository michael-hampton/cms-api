<?php

namespace App\Controllers;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Repositories\AuthorRepository;
use App\Requests\CreateAuthorRequest;
use App\Requests\UpdateAuthorRequest;
use App\Search\SearchCriteriaParser;
use App\Services\AuthorService;
use Exception;

class AuthorController extends Controller
{
    private AuthorService $authorService;

    public function __construct(AuthorService $authorService, private AuthorRepository $authorRepository)
    {
        $this->authorService = $authorService;
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request);
            $result = $this->authorRepository->search($criteria);

            return $this->searchResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreateAuthorRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $avatarFile = $request->hasFile('avatar') ? $request->file('avatar') : null;

            $author = $this->authorService->createAuthor($data, $avatarFile);

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

            $data = $author->toArray();
            if ($author->relationLoaded('pages')) {
                $data['pages'] = $author->pages->toArray();
            }

            return $this->jsonResponse(['author' => $data]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, UpdateAuthorRequest $request): JsonResponse
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

    public function destroy(int $id, Request $request): JsonResponse
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

    public function merge(Request $request): JsonResponse
    {
        try {
            $sourceId = $request->get('source_author_id');
            $targetId = $request->get('target_author_id');

            if (!$sourceId || !$targetId) {
                return $this->errorResponse('Both source and target author IDs are required', 400);
            }

            $result = $this->authorService->mergeAuthors((int)$sourceId, (int)$targetId);

            return $this->successResponse('Authors merged successfully');

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function checkDelete(int $id): JsonResponse
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
}