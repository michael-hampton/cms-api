<?php

namespace App\Enums\OpenCollab;

/**
 * Persisted values for ContributorProfile::author_sync_status.
 */
enum AuthorSyncStatus: string
{
    case Created = 'created';
    case Synced = 'synced';
    case PartiallySynced = 'partially_synced';
}
