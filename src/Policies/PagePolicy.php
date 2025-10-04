<?php

namespace App\Policies;

use App\Framework\AuthenticatedUser;
use App\Models\Page;

class PagePolicy
{
    public function view(?AuthenticatedUser $user, Page $page): bool
    {
        return $page->status === 'published' || ($user && $user->id === $page->author_id);
    }

    public function create(?AuthenticatedUser $user): bool
    {
        return $user !== null;
    }

    public function update(?AuthenticatedUser $user, Page $page): bool
    {
        return $user && $user->id === $page->author_id;
    }

    public function delete(?AuthenticatedUser $user, Page $page): bool
    {
        return $user && ($user->id === $page->author_id || $user->role === 'admin');
    }
}