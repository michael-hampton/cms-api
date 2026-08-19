<?php

namespace App\Enums\OpenCollab;

/**
 * Values written to Collaborator::role by CmsBriefGateway for the
 * contributor-facing brief assignment lifecycle.
 */
enum BriefCollaboratorRole: string
{
    case Writer = 'writer';
    case Rejected = 'rejected';
    case Negotiating = 'negotiating';
}
