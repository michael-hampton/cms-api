<?php

namespace App\Enums\OpenCollab;

/**
 * Values written to UserNotification::type by CmsBriefGateway via
 * OpenCollabBriefNotificationService.
 */
enum BriefNotificationType: string
{
    case AssignmentAccepted = 'brief_assignment_accepted';
    case SubmittedForApproval = 'brief_submitted_for_approval';
    case ResubmittedForApproval = 'brief_resubmitted_for_approval';
}
