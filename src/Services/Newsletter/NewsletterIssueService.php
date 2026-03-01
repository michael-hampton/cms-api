<?php

namespace App\Services\Newsletter;

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
 * Orchestrates newsletter issue lifecycle: creation, content persistence,
 * and delegating sends to NewsletterSendService.
 *
 * An issue is a content draft tied to a newsletter. It can be created,
 * edited, and eventually sent. Sending is a one-way transition.
 *
 * Responsibilities:
 *   - Validate and persist issue content
 *   - Guard against sending already-sent issues
 *   - Delegate actual dispatch to NewsletterSendService
 *   - Emit events for cross-cutting concerns
 *
 * Does NOT:
 *   - Build HTML (NewsletterContentBuilder)
 *   - Resolve recipients (NewsletterRecipientResolver)
 *   - Dispatch emails (NewsletterDispatcher)
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

    /**
     * Create a new draft issue for a newsletter.
     *
     * The issue starts in `draft` status. Content blocks are validated
     * if provided; an issue may also be created without blocks (empty draft).
     *
     * @throws \InvalidArgumentException on block validation failure
     * @throws \RuntimeException         if newsletter not found
     */
    public function createIssue(int $newsletterId, int $siteId, array $data): NewsletterIssue
    {
        return $this->database->transaction(function () use ($newsletterId, $siteId, $data) {
            $newsletter = $this->newsletterRepository->find($newsletterId);

            if (!$newsletter || $newsletter->site_id !== $siteId) {
                throw new \RuntimeException("Newsletter {$newsletterId} not found.");
            }

            $blocks = $data['content_blocks'] ?? null;

            if ($blocks !== null) {
                $this->blockPayloadValidator->validate($blocks);
            }

            $issue = $this->issueRepository->create([
                'newsletter_id' => $newsletterId,
                'site_id' => $siteId,
                'subject' => $data['subject'] ?? $newsletter->title,
                'content_blocks' => $blocks,
                'status' => NewsletterIssueStatus::Draft->value,
                'scheduled_at' => $data['scheduled_at'] ?? null,
            ]);

            $this->logger->info('Newsletter issue created', [
                'issue_id' => $issue->id,
                'newsletter_id' => $newsletterId,
                'site_id' => $siteId,
            ]);

            return $issue;
        });
    }

    /**
     * Send a newsletter issue to all eligible recipients.
     *
     * Delegates to NewsletterSendService which owns the full dispatch pipeline.
     * On success, the issue is transitioned to `sent` and linked to the
     * resulting NewsletterSend record.
     *
     * @throws \RuntimeException         if issue or newsletter not found
     * @throws \DomainException          if issue has already been sent
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

        // Temporarily override newsletter content blocks from the issue if the
        // issue carries its own blocks, so the send pipeline renders the correct
        // content without permanently mutating the newsletter.
        $newsletterForSend = $this->prepareNewsletterForIssue($newsletter, $issue);

        $sendResult = $this->sendService->sendNewsletter($newsletterForSend, $siteId, $actor);

        if (!$sendResult['success'] && empty($sendResult['partial_failure'])) {
            return $sendResult;
        }

        // Transition issue to sent and link to the send record.
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

    /**
     * If the issue has its own content blocks, temporarily override the
     * newsletter's content so the send pipeline uses the issue's content.
     *
     * This returns a cloned newsletter object — it does NOT persist changes.
     */
    private function prepareNewsletterForIssue(Newsletter $newsletter, NewsletterIssue $issue): Newsletter
    {
        if (empty($issue->content_blocks)) {
            return $newsletter;
        }

        // Clone via fill to avoid touching the persisted model.
        $clone = clone $newsletter;
        $clone->content_blocks = $issue->content_blocks;
        $clone->content_type = 'custom_blocks';

        return $clone;
    }

    /**
     * Update an existing draft issue.
     *
     * Sent issues are immutable — attempting to update one throws.
     *
     * @throws \RuntimeException  if issue not found
     * @throws \DomainException   if issue has been sent
     * @throws \InvalidArgumentException on block validation failure
     */
    public function updateIssue(int $issueId, int $siteId, array $data): NewsletterIssue
    {
        return $this->database->transaction(function () use ($issueId, $siteId, $data) {
            $issue = $this->issueRepository->find($issueId);

            if (!$issue || $issue->site_id !== $siteId) {
                throw new \RuntimeException("Issue {$issueId} not found.");
            }

            if ($issue->isSent()) {
                throw new \DomainException("Sent issues are immutable.");
            }

            $blocks = $data['content_blocks'] ?? null;

            if ($blocks !== null) {
                $this->blockPayloadValidator->validate($blocks);
            }

            $updates = array_filter([
                'subject' => $data['subject'] ?? null,
                'content_blocks' => $blocks,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'status' => isset($data['status'])
                    ? NewsletterIssueStatus::from($data['status'])->value
                    : null,
            ], fn($v) => $v !== null);

            return $this->issueRepository->update($issueId, $updates);
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Return all issues for a newsletter, ordered newest-first.
     */
    public function listIssues(int $newsletterId, int $siteId): array
    {
        return $this->issueRepository
            ->findByNewsletter($newsletterId, $siteId)
            ->toArray();
    }
}