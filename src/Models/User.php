<?php

namespace App\Models;

use App\Framework\AuthenticatedUser;

class User extends Model
{
    protected $table = 'users';

    public function verifyPassword(string $password)
    {
        return true; //todo
    }

    public function isActive()
    {
        return true; //todo
    }
}