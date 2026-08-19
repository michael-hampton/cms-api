<?php

namespace App\Enums\OpenCollab;

/**
 * Persisted values for ContributorAuthorSyncAudit::event.
 */
enum AuthorSyncAuditEvent: string
{
    case AuthorCreated = 'author_created';
    case AuthorLinked = 'author_linked';
    case ProfileSynced = 'profile_synced';
    case SyncSkipped = 'sync_skipped';
    case AdminOverride = 'admin_override';
    case OverrideRemoved = 'override_removed';
}
