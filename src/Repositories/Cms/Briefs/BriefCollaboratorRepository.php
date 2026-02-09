<?php

namespace App\Repositories\Cms\Briefs;

use App\Models\Brief;
use App\Models\Collaborator;
use App\Models\Model;
use App\Repositories\Cms\CollaboratorRepository;

class BriefCollaboratorRepository extends CollaboratorRepository
{
    public function getForBrief(int $briefId): array
    {
        return $this->getForCollaboratable(Brief::class, $briefId)->toArray();
    }

    public function removeForUser(int $id, int $userId, string $type = 'brief'): bool
    {
        return parent::removeForUser($id, $userId, Brief::class);
    }

    public function findByBriefAndUser(int $briefId, int $userId): ?Collaborator
    {
        return $this->findByCollaboratableAndUser(Brief::class, $briefId, $userId);
    }

    public function createForBrief(int $briefId, array $data): Model
    {
        return $this->createForCollaboratable(Brief::class, $briefId, $data);
    }
}