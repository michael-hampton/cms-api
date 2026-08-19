<?php

namespace App\Enums\OpenCollab;

/**
 * Brief::status value(s) that CmsBriefGateway transitions a brief into.
 *
 * Note: this intentionally only covers the value(s) this gateway writes.
 * The full brief workflow status set is owned by Services\Cms\BriefService
 * and is out of scope here.
 */
enum BriefSubmissionStatus: string
{
    case InReview = 'in_review';
}
