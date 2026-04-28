<?php

namespace App\Services;

use App\Enums\OpenCollab\NotificationType;
use App\Models\UserNotification;

/**
 * Formats a UserNotification into a presentation-ready array.
 *
 * This is the only place where notification display strings live.
 * All cases in NotificationType must be covered — the default arm
 * acts as a safety net for types added before this class is updated.
 *
 * Format contract:
 *   title      string
 *   message    string
 *   action_url string|null
 */
class NotificationFormatter
{
    public function format(UserNotification $notification): array
    {
        $type = NotificationType::tryFrom($notification->type);

        if ($type === null) {
            return $this->fallback();
        }

        return match ($type) {

            // ── Content lifecycle ─────────────────────────────────────────────
            NotificationType::ArticleApproved => [
                'title' => 'Article approved',
                'message' => 'Your article has been approved and is now live.',
                'action_url' => '/articles/' . ($notification->data['article_id'] ?? ''),
            ],

            NotificationType::ArticleRejected => [
                'title' => 'Article rejected',
                'message' => 'Your article was rejected. Please check the feedback.',
                'action_url' => '/articles/' . ($notification->data['article_id'] ?? ''),
            ],

            NotificationType::ArticleNeedsChanges => [
                'title' => 'Changes requested',
                'message' => 'Your article needs changes before it can be approved.',
                'action_url' => '/articles/' . ($notification->data['article_id'] ?? ''),
            ],

            NotificationType::ArticleSubmitted => [
                'title' => 'Article submitted',
                'message' => 'Your article has been submitted for review.',
                'action_url' => '/articles/' . ($notification->data['article_id'] ?? ''),
            ],

            // ── Earnings / Payouts ────────────────────────────────────────────
            NotificationType::PayoutProcessed => [
                'title' => 'Payout processed',
                'message' => '£' . number_format((float)($notification->data['amount'] ?? 0), 2)
                    . ' has been sent to your account.',
                'action_url' => '/contributor/earnings',
            ],

            NotificationType::PayoutFailed => [
                'title' => 'Payout failed',
                'message' => 'Your payout could not be processed. Please check your payment details.',
                'action_url' => '/contributor/earnings',
            ],

            NotificationType::EarningsThresholdReached => [
                'title' => 'Payout threshold reached',
                'message' => 'Your earnings have reached the payout threshold.',
                'action_url' => '/contributor/earnings',
            ],

            // ── Disputes ─────────────────────────────────────────────────────
            NotificationType::DisputeRaised => [
                'title' => 'Dispute opened',
                'message' => 'A new dispute has been raised on your content.',
                'action_url' => '/open-collab/disputes/' . ($notification->data['dispute_id'] ?? ''),
            ],

            NotificationType::DisputeUpdated => [
                'title' => 'Dispute updated',
                'message' => 'There is an update on your dispute.',
                'action_url' => '/open-collab/disputes/' . ($notification->data['dispute_id'] ?? ''),
            ],

            NotificationType::DisputeResolved => [
                'title' => 'Dispute resolved',
                'message' => 'Your dispute has been resolved.',
                'action_url' => '/open-collab/disputes/' . ($notification->data['dispute_id'] ?? ''),
            ],

            // ── Contracts / Platform ──────────────────────────────────────────
            NotificationType::ContractPublished => [
                'title' => 'New contract published',
                'message' => 'A new contributor agreement is available for review.',
                'action_url' => '/open-collab/settings#compliance',
            ],

            NotificationType::ContractUpdated => [
                'title' => 'Contract updated',
                'message' => 'The contributor agreement has been updated.',
                'action_url' => '/open-collab/settings#compliance',
            ],

            // ── Moderation / Account ──────────────────────────────────────────
            NotificationType::ViolationRecorded => [
                'title' => 'Violation recorded',
                'message' => 'A content violation has been recorded on your account.',
                'action_url' => '/open-collab/disputes/' . ($notification->data['dispute_id'] ?? ''),
            ],

            NotificationType::AccountFlagged => [
                'title' => 'Account flagged',
                'message' => 'Your account has been flagged. Please contact support.',
                'action_url' => null,
            ],

            NotificationType::GuidelinesVersionBump => [
                'title' => 'Guidelines updated',
                'message' => 'The brand guidelines have been updated. Please review and acknowledge.',
                'action_url' => '/open-collab/settings#compliance',
            ],

            NotificationType::ImportantSystemUpdate => [
                'title' => 'Important update',
                'message' => $notification->data['message'] ?? 'There is an important platform update.',
                'action_url' => $notification->data['action_url'] ?? null,
            ],
        };
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function fallback(): array
    {
        return [
            'title' => 'Notification',
            'message' => 'You have a new update.',
            'action_url' => null,
        ];
    }
}