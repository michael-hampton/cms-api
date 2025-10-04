<?php

namespace App\Framework\Authorization;

use App\Framework\AuthenticatedUser;

abstract class Policy
{
    abstract public function viewAny(AuthenticatedUser $user): bool;
    abstract public function view(AuthenticatedUser $user, $model): bool;
    abstract public function create(AuthenticatedUser $user): bool;
    abstract public function update(AuthenticatedUser $user, $model): bool;
    abstract public function delete(AuthenticatedUser $user, $model): bool;
}