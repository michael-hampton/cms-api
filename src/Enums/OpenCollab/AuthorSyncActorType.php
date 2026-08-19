<?php

namespace App\Enums\OpenCollab;

/**
 * Persisted values for ContributorAuthorSyncAudit::actor_type and
 * Author::last_updated_by_type.
 */
enum AuthorSyncActorType: string
{
    case System = 'system';
    case Contributor = 'contributor';
    case Admin = 'admin';
}
