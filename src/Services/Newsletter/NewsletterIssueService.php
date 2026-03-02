<?php

namespace App\Services\Newsletter;

use App\DTO\Newsletters\IssueManualSendDTO;
use App\DTO\Newsletters\NewsletterIssueDTO;
use App\Enums\Newsletters\NewsletterIssueStatus;
use App\Events\Newsletters\NewsletterIssueSent;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Member;
use App\Models\Newsletter;
use App\Models\NewsletterIssue;
use App\Repositories\Newsletters\NewsletterIssueRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Services\Newsletter\Validation\BlockPayloadValidator;

/**
 * Orchestrates the newsletter issue lifecycle:
 *   - Snapshot creation (with auto-incrementing issue_number)
 *   - Snapshot revert (returning immutable data; does NOT mutate the newsletter)
 *   - Delegated sends via NewsletterSendService
 *   - Manual ad-hoc sends to all subscribers or a custom email list
 *
 * Responsibilities:
 *   - Validate and persist issue data
 *   - Guard against sending already-sent issues
 *   - Delegate actual dispatch to NewsletterSendService
 *   - Emit domain events for cross-cutting concerns
 *
 * Does NOT:
 *   - Build HTML (NewsletterContentBuilder)
 *   - Resolve recipients (NewsletterRecipientResolver)
 *   - Dispatch emails directly (NewsletterDispatcher)
 */
class NewsletterIssueService
{
    public function __construct(
        private readonly NewsletterIssueRepository $issueRepository,
        private readonly NewsletterRepository      $newsletterRepository,
        private readonly NewsletterSendService     $sendService,
        private readonly BlockPayloadValidator     $blockPayloadValidator,
        private readonly Logger                    $logger,
        private readonly Database                  $database,
    )
    {
    }

    // =========================================================================
    // Issue creation
    // =========================================================================

    /**
     * Create a new draft issue from a DTO, optionally including a snapshot of
     * the current editor state (layout + blocks + metadata).
     *
     * issue_number is auto-incremented per newsletter, not globally.
     *
     * @throws \InvalidArgumentException on block validation failure
     * @throws \RuntimeException         if newsletter not found
     */
    public function createIssue(int $newsletterId, int $siteId, NewsletterIssueDTO $dto): NewsletterIssue
    {
        return $this->database->transaction(function () use ($newsletterId, $siteId, $dto) {
            $newsletter = $this->newsletterRepository->find($newsletterId);

            if (!$newsletter || $newsletter->site_id !== $siteId) {
                throw new \RuntimeException("Newsletter {$newsletterId} not found.");
            }

            if ($dto->contentBlocks !== null) {
                $this->blockPayloadValidator->validate($dto->contentBlocks);
            }

            $nextNumber = $this->issueRepository->getMaxIssueNumber($newsletterId) + 1;

            $issue = $this->issueRepository->create([
                'newsletter_id' => $newsletterId,
                'site_id' => $siteId,
                'issue_number' => $nextNumber,
                'subject' => $dto->subject ?? $newsletter->title,
                'content_blocks' => $dto->contentBlocks,
                'snapshot_json' => $dto->snapshotJson,
                'status' => NewsletterIssueStatus::Draft->value,
                'scheduled_at' => $dto->scheduledAt,
            ]);

            $this->logger->info('Newsletter issue created', [
                'issue_id' => $issue->id,
                'issue_number' => $nextNumber,
                'newsletter_id' => $newsletterId,
                'site_id' => $siteId,
            ]);

            return $issue;
        });
    }

    // =========================================================================
    // Revert
    // =========================================================================

    /**
     * Return the snapshot payload for an issue so the frontend can restore the
     * editor state.  This operation is read-only — it does NOT mutate the
     * newsletter record.
     *
     * @throws \RuntimeException if issue not found or belongs to a different site/newsletter
     */
    public function getIssueSnapshot(int $newsletterId, int $issueId, int $siteId): array
    {
        $issue = $this->issueRepository->find($issueId);

        if (!$issue || $issue->site_id !== $siteId || $issue->newsletter_id !== $newsletterId) {
            throw new \RuntimeException("Issue {$issueId} not found.");
        }

        return [
            'issue' => $issue,
            'snapshot_json' => $issue->snapshot_json,
        ];
    }

    // =========================================================================
    // Scheduled send (existing pipeline — unchanged)
    // =========================================================================

    /**
     * Send a newsletter issue through the standard dispatch pipeline.
     * Delegates to NewsletterSendService which owns recipient resolution,
     * HTML building, and queuing.
     *
     * @throws \RuntimeException  if issue or newsletter not found
     * @throws \DomainException   if issue has already been sent
     */
    public function sendIssue(int $issueId, int $siteId, ?Member $actor = null): array
    {
        $issue = $this->issueRepository->find($issueId);

        if (!$issue || $issue->site_id !== $siteId) {
            throw new \RuntimeException("Issue {$issueId} not found.");
        }

        if ($issue->isSent()) {
            throw new \DomainException("Issue {$issueId} has already been sent and cannot be re-sent.");
        }

        $newsletter = $this->newsletterRepository->find($issue->newsletter_id);

        if (!$newsletter || $newsletter->site_id !== $siteId) {
            throw new \RuntimeException("Newsletter {$issue->newsletter_id} not found.");
        }

        $newsletterForSend = $this->prepareNewsletterForIssue($newsletter, $issue);

        $sendResult = $this->sendService->sendNewsletter($newsletterForSend, $siteId, $actor);

        if (!$sendResult['success'] && empty($sendResult['partial_failure'])) {
            return $sendResult;
        }

        $this->database->transaction(function () use ($issueId, $sendResult) {
            $this->issueRepository->update($issueId, [
                'status' => NewsletterIssueStatus::Sent->value,
                'send_id' => $sendResult['send_id'] ?? null,
                'sent_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        });

        event(new NewsletterIssueSent($issue, $sendResult));

        $this->logger->info('Newsletter issue sent', [
            'issue_id' => $issueId,
            'send_id' => $sendResult['send_id'] ?? null,
            'recipients' => $sendResult['recipients'] ?? 0,
        ]);

        return array_merge($sendResult, ['issue_id' => $issueId]);
    }

    // =========================================================================
    // Manual ad-hoc send
    // =========================================================================

    /**
     * Manually dispatch an issue to either all subscribers or a custom list of
     * email addresses, as specified in the DTO.
     *
     * Unlike sendIssue(), this does NOT transition the issue to "sent" status —
     * it is an out-of-band preview/blast that does not affect the issue lifecycle.
     * Sending is queued asynchronously via NewsletterSendService.
     *
     * @throws \RuntimeException  if issue not found
     * @throws \DomainException   if issue has already been sent through the normal pipeline
     */
    public function manualSendIssue(int $issueId, int $siteId, IssueManualSendDTO $dto, ?Member $actor = null): array
    {
        $issue = $this->issueRepository->find($issueId);

        if (!$issue || $issue->site_id !== $siteId) {
            throw new \RuntimeException("Issue {$issueId} not found.");
        }

        $newsletter = $this->newsletterRepository->find($issue->newsletter_id);

        if (!$newsletter || $newsletter->site_id !== $siteId) {
            throw new \RuntimeException("Newsletter {$issue->newsletter_id} not found.");
        }

        $newsletterForSend = $this->prepareNewsletterForIssue($newsletter, $issue);

        if ($dto->isCustom()) {
            $sendResult = $this->sendService->sendToCustomEmails(
                $newsletterForSend,
                $dto->customEmails,
                $siteId,
                $actor
            );
        } else {
            $sendResult = $this->sendService->sendNewsletter($newsletterForSend, $siteId, $actor);
        }

        $this->logger->info('Newsletter issue manual send queued', [
            'issue_id' => $issueId,
            'send_type' => $dto->sendType,
            'recipients' => $dto->isCustom() ? count($dto->customEmails) : 'all',
        ]);

        return array_merge($sendResult, [
            'queued' => true,
            'message' => $dto->isCustom()
                ? sprintf('Issue queued for %d recipient(s)', count($dto->customEmails))
                : 'Issue queued for all subscribers',
        ]);
    }

    // =========================================================================
    // Issue update
    // =========================================================================

    /**
     * Update a draft issue.  Sent issues are immutable.
     *
     * @throws \RuntimeException     if issue not found
     * @throws \DomainException      if issue has been sent
     * @throws \InvalidArgumentException on block validation failure
     */
    public function updateIssue(int $issueId, int $siteId, NewsletterIssueDTO $dto): NewsletterIssue
    {
        return $this->database->transaction(function () use ($issueId, $siteId, $dto) {
            $issue = $this->issueRepository->find($issueId);

            if (!$issue || $issue->site_id !== $siteId) {
                throw new \RuntimeException("Issue {$issueId} not found.");
            }

            if ($issue->isSent()) {
                throw new \DomainException("Sent issues are immutable.");
            }

            if ($dto->contentBlocks !== null) {
                $this->blockPayloadValidator->validate($dto->contentBlocks);
            }

            $updates = array_filter([
                'subject' => $dto->subject,
                'content_blocks' => $dto->contentBlocks,
                'snapshot_json' => $dto->snapshotJson,
                'scheduled_at' => $dto->scheduledAt,
            ], fn($v) => $v !== null);

            return $this->issueRepository->update($issueId, $updates);
        });
    }

    // =========================================================================
    // List
    // =========================================================================

    public function listIssues(int $newsletterId, int $siteId): array
    {
        return $this->issueRepository
            ->findByNewsletter($newsletterId, $siteId)
            ->toArray();
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Clone the newsletter model substituting the issue's content blocks so
     * the send pipeline renders the correct content.  Does NOT persist.
     */
    private function prepareNewsletterForIssue(Newsletter $newsletter, NewsletterIssue $issue): Newsletter
    {
        if (!$issue->content_blocks) {
            return $newsletter;
        }

        $clone = clone $newsletter;
        $clone->content_blocks = $issue->content_blocks;
        $clone->content_type = 'custom_blocks';

        return $clone;
    }
}