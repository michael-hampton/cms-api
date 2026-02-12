<?php

namespace App\Repositories\Cms;

use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Models\Collaborator;
use App\Models\Model;
use App\Repositories\Repository;

class CollaboratorRepository extends Repository
{
    public function getForCollaboratable(string $type, int $id): Collection
    {
        return Collaborator::where('collaboratable_type', $type)
            ->where('collaboratable_id', $id)
            ->with(['user'])
            ->orderBy('created_at')
            ->get();
    }

    public function removeForUser(int $id, int $userId, string $type = 'brief'): bool
    {
        return Collaborator::where('collaboratable_type', $type)
                ->where('collaboratable_id', $id)
                ->where('user_id', $userId)
                ->delete() > 0;
    }

    public function remove(int $id, string $type = 'brief'): bool
    {
        return Collaborator::where('collaboratable_type', $type)
                ->where('id', $id)
                ->delete() > 0;
    }

    public function findByCollaboratableAndUser(string $type, int $id, int $userId): ?Collaborator
    {
        return Collaborator::where('collaboratable_type', $type)
            ->where('collaboratable_id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function createForCollaboratable(string $type, int $id, array $data, ?int $siteId = null): Model
    {
        $siteId = $siteId ?? SiteContext::getId();
        return $this->create(array_merge([
            'collaboratable_type' => $type,
            'collaboratable_id' => $id,
            'site_id' => $siteId
        ], $data));
    }

    protected function getModelClass(): string
    {
        return Collaborator::class;
    }
}