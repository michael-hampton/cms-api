<?php

namespace App\Services\Cms;

use App\Framework\Support\Hash;
use App\Models\Model;
use App\Repositories\Cms\UserRepository;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;

class UserService
{
    public function __construct(
        protected UserRepository $userRepository
    ) {
    }

    public function searchUsers(SearchCriteria $criteria): PaginatedResult
    {
        return $this->userRepository->search($criteria);
    }

    public function getAllUsers(int $perPage = 15): array
    {
        return $this->userRepository->paginate($perPage);
    }

    public function getUserById(int $id): ?Model
    {
        return $this->userRepository->find($id);
    }

    public function createUser(array $data): Model
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return $this->userRepository->create($data);
    }

    public function updateUser(int $id, array $data): Model
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        return $this->userRepository->update($id, $data);
    }

    public function deleteUser(int $id): bool
    {
        return $this->userRepository->delete($id);
    }

    public function isEmailTaken(string $email, int $siteId, ?int $excludeUserId = null): bool
    {
        $user = $this->userRepository->findByEmail($email, $siteId);

        if (!$user) {
            return false;
        }

        if ($excludeUserId && $user->id === $excludeUserId) {
            return false;
        }

        return true;
    }
}