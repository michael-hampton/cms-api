<?php

namespace App\Events\Newsletters;

use App\Models\NewsletterIssue;

/**
 * Fired after a newsletter issue has been successfully sent.
 *
 * Listeners may use this event for:
 *   - Analytics recording
 *   - Slack/webhook notifications to editors
 *   - Updating external CRM records
 */
class NewsletterIssueSent
{
    public function __construct(
        public readonly NewsletterIssue $issue,
        public readonly array           $sendResult,
    )
    {
    }
}