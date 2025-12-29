<?php

namespace App\Policies;

use App\Framework\AuthenticatedUser;
use App\Models\Model;

abstract class BasePolicy
{
    /**
     * Default authorization methods
     */
    public function viewAny(AuthenticatedUser $user): bool
    {
        return true; // Override in child classes
    }

    public function view(AuthenticatedUser $user, Model $model): bool
    {
        return true; // Override in child classes
    }

    public function create(AuthenticatedUser $user): bool
    {
        return $this->isEditor($user);
    }

    /**
     * Check if user is editor or above
     */
    protected function isEditor(AuthenticatedUser $user): bool
    {
        return in_array($user->role, ['admin', 'editor']);
    }

    public function update(AuthenticatedUser $user, Model $model): bool
    {
        return $this->isEditor($user);
    }

    public function delete(AuthenticatedUser $user, Model $model): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Check if user is admin
     */
    protected function isAdmin(AuthenticatedUser $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Check if user owns the model
     */
    protected function owns(AuthenticatedUser $user, Model $model): bool
    {
        return isset($model->user_id) && $model->created_by === $user->id;
    }
}