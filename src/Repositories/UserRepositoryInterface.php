<?php

namespace App\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email, int $siteId): ?User;
    public function findById(int $id, int $siteId): ?User;
}