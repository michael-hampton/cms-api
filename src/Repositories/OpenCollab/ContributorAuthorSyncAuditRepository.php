<?php

namespace App\Repositories\OpenCollab;

use App\Models\ContributorAuthorSyncAudit;
use App\Models\Model;
use App\Repositories\Repository;

class ContributorAuthorSyncAuditRepository extends Repository
{
    public function log(
        ?int $profileId,
        ?int $authorId,
        ?int $siteId,
        string $actorType,
        ?int $actorId,
        string $event,
        array $fields = [],
        array $metadata = [],
    ): Model {
        return ContributorAuthorSyncAudit::create([
            'contributor_profile_id' => $profileId,
            'author_id' => $authorId,
            'site_id' => $siteId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'event' => $event,
            'fields' => $fields,
            'metadata' => $metadata,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function getModelClass(): string
    {
        return ContributorAuthorSyncAudit::class;
    }
}
