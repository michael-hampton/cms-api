<?php

namespace App\Repositories\Cms;

use App\Models\Model;
use App\Models\User;

interface UserRepositoryInterface
{
    public function find(int $id): ?Model;
    public function findByEmail(string $email, ?int $siteId = null): ?User;
    public function findById(int $id, int $siteId): ?User;
    public function create(array $data): Model;
    public function update(int $id, array $data): ?Model;

    public function updateUserWithPassword(int $id, array $data): Model;
}
