<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository implements UserRepositoryInterface
{

    public function findByEmail(string $email, int $siteId): ?User
    {
        $user = User::where('email', $email)
            //->where('site_id', $siteId)
            ->first();

       return new User($user);
    }

    public function findById(int $id, int $siteId): ?User
    {
        $user = User::where('id', $id)
           // ->where('site_id', $siteId)
            ->first();

        return !empty($user) ? new User($user) : null;
    }
}