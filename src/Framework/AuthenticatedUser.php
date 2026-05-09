<?php

namespace App\Framework;

use App\Framework\Authorization\Gate;
use App\Models\Model;

class AuthenticatedUser extends Model
{
    public int $id;
    public string $name;
    public string $email;
    public string $role;

    public function __construct(int $id, string $name, string $email, string $role = 'user')
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->role = $role;

        parent::__construct();
    }

    /**
     * Check if user can perform an ability on a model
     */
    public function can(string $ability, $model = null): bool
    {
        // Simple implementation - in real app would use Gates and Policies
//        if ($this->role === 'admin') {
//            return true;
//        }

        return Gate::allows($ability, [$this, $model]);
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }
}