<?php

namespace App\Enums\OpenCollab;

/**
 * Values written to BriefActivityLog::action by CmsBriefGateway.
 */
enum BriefActivityAction: string
{
    case AssignmentAccepted = 'assignment_accepted';
    case SubmissionNotesAdded = 'submission_notes_added';
    case BriefResubmitted = 'brief_resubmitted';
    case TaskUpdated = 'task_updated';
    case AttachmentUploaded = 'attachment_uploaded';
    case AttachmentDeleted = 'attachment_deleted';
    case CommentAdded = 'comment_added';
}
