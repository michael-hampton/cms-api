<?php

declare(strict_types=1);

namespace App\Enums\Subscriptions;

/**
 * Classifies a SubscriptionIssueFulfilment by how it must be physically dispatched.
 *
 *   STANDARD   — created by the normal subscription renewal/scheduling pipeline
 *                (IssueFulfilmentPlanner) and picked up by the Label Run workflow
 *                (GenerateLabelRunsJob) the first time its issue's batch is run.
 *
 *   BACK_ISSUE — a single-issue purchase of an issue that is already printed
 *                (its Label Run has already completed, or it already went on
 *                sale). The normal Label Run workflow only ever processes a
 *                given batch once, so a fulfilment created after that point
 *                would never be picked up. These are instead dispatched via
 *                BackIssueReplacementCopyDispatchService, which extracts
 *                BACK_ISSUE rows on every run.
 */
enum FulfilmentTypeEnum: string
{
    case STANDARD = 'standard';
    case BACK_ISSUE = 'back_issue';
}
