<?php

namespace App\Controllers;

use App\Exceptions\CannotDeleteException;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\Request;
use App\Framework\Http\JsonResponse;
use App\Framework\Validation\ValidationResult;
use App\Framework\Validation\Validator;
use App\Repositories\CategoryRepository;
use App\Requests\CreateCategoryRequest;
use App\Requests\UpdateCategoryRequest;
use App\Search\SearchCriteriaParser;
use App\Services\CategoryService;
use Exception;

class CategoryController extends Controller
{
    private $categoryRepository;
    private $validator;

    public function __construct(CategoryRepository $categoryRepository, Validator $validator, private CategoryService $categoryService)
    {
        $this->categoryRepository = $categoryRepository;
        $this->validator = $validator;
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request);

            $result = $this->categoryRepository->search($criteria);

            return $this->searchResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function tree(): JsonResponse
    {
        try {
            $tree = $this->categoryRepository->getCategoryTree();
            return $this->jsonResponse(['tree' => $tree]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $category = is_numeric($id)
                ? $this->categoryRepository->find((int)$id)
                : $this->categoryRepository->findBySlug($id);

            if (!$category) {
                return $this->errorResponse('Category not found', 404);
            }

            return $this->jsonResponse(['category' => $category->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreateCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->categoryRepository->create($request->validated());
            return $this->jsonResponse(['category' => $category->toArray()], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function update(int $id, UpdateCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->categoryRepository->update($id, $request->validated());
            if (!$category) {
                return $this->errorResponse('Category not found', 404);
            }
            return $this->jsonResponse(['category' => $category->toArray()]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $reassignToId = $request->input('reassignId');
            $this->categoryService->delete($id, $reassignToId);

            return $this->jsonResponse([
                'message' => 'Category deleted successfully'
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
                'message' => 'Category not found'
            ], 404);
        }
    }

    public function popular(): JsonResponse
    {
        try {
            $categories = $this->categoryRepository->getPopularCategories(20);
            return $this->jsonResponse(['categories' => array_map(fn($cat) => $cat->toArray(), $categories)]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function checkDelete(int $id): JsonResponse
    {
        try {
            $result = $this->categoryService->checkDeletable($id);

            if ($result['requires_reassignment']) {
                $alternatives = $this->categoryService->getAlternativeCategories($id);
                $result['alternatives'] = $alternatives;
            }

            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'message' => 'Category not found'
            ], 404);
        }
    }
}