<?php

namespace App\Enums\OpenCollab;

/**
 * Canonical registry of contributor notification event types.
 *
 * These values are stored in user_notifications.type and used as
 * consent_types.code for preference matching.
 *
 * They are grouped logically to mirror the consent_types.category column.
 */
enum NotificationType: string
{
    // ── Content lifecycle ─────────────────────────────────────────────────────
    case ArticleApproved = 'article_approved';
    case ArticleRejected = 'article_rejected';
    case ArticleNeedsChanges = 'article_needs_changes';
    case ArticleSubmitted = 'article_submitted';

    // ── Earnings / Payouts ────────────────────────────────────────────────────
    case PayoutProcessed = 'payout_processed';
    case PayoutFailed = 'payout_failed';
    case EarningsThresholdReached = 'earnings_threshold_reached';

    // ── Disputes ──────────────────────────────────────────────────────────────
    case DisputeRaised = 'dispute_raised';
    case DisputeUpdated = 'dispute_updated';
    case DisputeResolved = 'dispute_resolved';

    // ── Contracts / Platform ──────────────────────────────────────────────────
    case ContractPublished = 'contract_published';
    case ContractUpdated = 'contract_updated';

    // ── Moderation / Account ──────────────────────────────────────────────────
    case ViolationRecorded = 'violation_recorded';
    case AccountFlagged = 'account_flagged';
    case GuidelinesVersionBump = 'guidelines_version_bump';
    case ImportantSystemUpdate = 'important_system_update';

    /**
     * Returns the logical category for this type, matching consent_types.category.
     */
    public function category(): string
    {
        return match ($this) {
            self::ArticleApproved,
            self::ArticleRejected,
            self::ArticleNeedsChanges,
            self::ArticleSubmitted => 'content',

            self::PayoutProcessed,
            self::PayoutFailed,
            self::EarningsThresholdReached => 'earnings',

            self::DisputeRaised,
            self::DisputeUpdated,
            self::DisputeResolved => 'disputes',

            self::ContractPublished,
            self::ContractUpdated => 'contracts',

            self::ViolationRecorded,
            self::AccountFlagged,
            self::GuidelinesVersionBump,
            self::ImportantSystemUpdate => 'account',
        };
    }

    /**
     * Whether this type is required (cannot be opted out of).
     * Mirrors is_required in consent_types but available without a DB call.
     */
    public function isRequired(): bool
    {
        return match ($this) {
            self::AccountFlagged,
            self::ImportantSystemUpdate,
            self::ContractPublished,
            self::ViolationRecorded => true,
            default => false,
        };
    }
}