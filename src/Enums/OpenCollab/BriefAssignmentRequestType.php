<?php

namespace App\Enums\OpenCollab;

enum BriefAssignmentRequestType: string
{
    case Clarification  = 'clarification';
    case DeadlineChange = 'deadline_change';
    case Negotiation    = 'negotiation';
    case Rejection      = 'rejection';
}