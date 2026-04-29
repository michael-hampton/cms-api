<?php

namespace App\Enums\OpenCollab;

enum AdminAction: string
{
    case CONTRIBUTOR_DEACTIVATED = 'contributor.deactivated';
    case CONTRIBUTOR_REACTIVATED = 'contributor.reactivated';
    case CONTRIBUTOR_ROLE_CHANGED = 'contributor.role_changed';
    case CONTRIBUTOR_CLOSED = 'contributor.closed';
    case SITE_ACCESS_GRANTED = 'contributor.site_access_granted';
    case SITE_ACCESS_REVOKED = 'contributor.site_access_revoked';
}