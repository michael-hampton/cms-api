<?php

namespace App\Controllers;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Requests\StoreUserRequest;
use App\Requests\UpdateUserRequest;
use App\Search\SearchCriteriaParser;
use App\Services\Cms\UserService;
use Exception;

class UserController extends Controller
{
    public function __construct(protected UserService $userService)
    {
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $result = $this->userService->searchUsers($criteria);

            return $this->searchResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);

        if (!$user) {
            return $this->resourceResponse([
                'message' => 'User not found'
            ], 404);
        }

        return $this->resourceResponse(['user' => $user->toArray()]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->createUser($request->validated());

            return $this->jsonResponse($user->toArray(), 201);
        } catch (ValidationException $exception) {
            return $this->jsonResponse($exception->getErrors(), 422);
        }
    }

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->userService->getUserById($id);

            if (!$user) {
                return $this->jsonResponse([
                    'message' => 'User not found'
                ], 404);
            }

            $this->userService->updateUser($id, $request->validated());

            $updatedUser = $this->userService->getUserById($id);

            return $this->jsonResponse($updatedUser->toArray(), 200);
        } catch (ValidationException $exception) {
            return $this->jsonResponse($exception->getErrors(), 422);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);

        if (!$user) {
            return $this->jsonResponse([
                'message' => 'User not found'
            ], 404);
        }

        $this->userService->deleteUser($id);

        return $this->jsonResponse([], 204);
    }
}