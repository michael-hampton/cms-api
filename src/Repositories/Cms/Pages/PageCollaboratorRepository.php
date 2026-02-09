<?php

namespace App\Repositories\Cms\Pages;

use App\Models\Collaborator;
use App\Models\Model;
use App\Models\Page;
use App\Repositories\Cms\CollaboratorRepository;

class PageCollaboratorRepository extends CollaboratorRepository
{
    public function getForPage(int $pageId): array
    {
        return $this->getForCollaboratable(Page::class, $pageId)->toArray();
    }

    public function removeForUser(int $id, int $userId, string $type = 'page'): bool
    {
        return parent::removeForUser($id, $userId, Page::class);
    }

    public function findByPageAndUser(int $pageId, int $userId): ?Collaborator
    {
        return $this->findByCollaboratableAndUser(Page::class, $pageId, $userId);
    }

    public function createForPage(int $pageId, array $data): Model
    {
        return $this->createForCollaboratable(Page::class, $pageId, $data);
    }
}