<?php

namespace App\Services\Newsletter;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Models\Model;
use App\Models\Newsletter;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendRecipientRepository;
use App\Repositories\Newsletters\NewsletterSendRepository;
use App\Repositories\Newsletters\NewsletterSnapshotRepository;
use App\Repositories\Newsletters\SubscriberRepository;
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Services\Cms\Pages\BlockParserService;
use App\Services\MemberInsights\EmailService;
use DateTimeImmutable;

class NewsletterSendService
{
    private const PREVIEW_RECIPIENT_LIMIT = 10;

    public function __construct(
        private readonly BlockParserService                     $parser,
        private readonly EmailService                           $emailService,
        private readonly SubscriberRepository                   $subscriberRepository,
        private readonly NewsletterRepository                   $newsletterRepository,
        private readonly NewsletterSendRepository               $sendRepository,
        private readonly MemberSubscriptionPreferenceRepository $preferenceRepository,
        private readonly NewsletterPageBuilderService      $pageBuilderService,
        private readonly MemberRepository                  $memberRepository,
        private readonly NewsletterSendRecipientRepository $recipientRepository,
        private readonly NewsletterContentBuilder          $contentBuilder,
        private readonly NewsletterRecipientResolver       $recipientResolver,
        private readonly NewsletterDispatcher              $dispatcher,
        private readonly Database                          $database,
        private readonly NewsletterSnapshotRepository      $snapshotRepository,
        private readonly NewsletterViewTokenService        $viewTokenService,
    )
    {
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function sendDueNewsletters(?int $siteId = null, ?Member $member = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();
        $newsletters = $this->newsletterRepository->getDueNewsletters($siteId);
        $results = [];

        foreach ($newsletters as $newsletter) {
            $results[] = $this->sendNewsletter($newsletter, $siteId, $member);
        }

        return $results;
    }

    public function sendNewsletter(Newsletter $newsletter, ?int $siteId = null, ?Member $member = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();

        if ($this->isDuplicateSend($newsletter, $siteId)) {
            Logger::warning('Duplicate send attempt prevented', ['newsletter_id' => $newsletter->id]);
            return [
                'success' => false,
                'newsletter_id' => $newsletter->id,
                'error' => 'Newsletter already sent recently',
            ];
        }

        $contentResult = $this->contentBuilder->build($newsletter, $siteId, false, $member, true);
        if (!$contentResult['success']) {
            return $contentResult;
        }

        $resolutionResult = $this->recipientResolver->resolveForNewsletter($newsletter, $siteId);
        $validRecipients = $resolutionResult['valid'];
        $skipped = $resolutionResult['skipped'];

        if (empty($validRecipients)) {
            Logger::warning('No eligible recipients after filtering', [
                'newsletter_id' => $newsletter->id,
                'skipped_count' => count($skipped),
            ]);
            return [
                'success' => false,
                'newsletter_id' => $newsletter->id,
                'error' => 'No eligible recipients',
                'skipped' => $this->formatSkippedList($skipped),
            ];
        }

        return $this->database->transaction(function () use ($newsletter, $validRecipients, $contentResult, $siteId, $skipped) {
            // 1. Persist a snapshot of the rendered HTML so "view in browser" can
            //    serve it later.  The snapshot is created here — inside the
            //    transaction — so that if anything fails the snapshot is rolled
            //    back with the rest of the send record.
            $snapshot = $this->snapshotRepository->createSnapshot(
                newsletterId: $newsletter->id,
                htmlSnapshot: $contentResult['html'],
                brandingSnapshot: null,
                layoutVersionId: null,
                brandingVersionId: null,
            );

            // 2. Generate a single view token for this snapshot.  The token is the
            //    same for all recipients; the per-recipient `r=` query-string
            //    parameter (added at dispatch time) provides attribution.
            $snapshotViewToken = $this->viewTokenService->generateTokenForSnapshot($snapshot->id);

            // 3. Inject the snapshot token into the HTML so the dispatcher can
            //    build the full per-recipient URL by appending `?r={recipientToken}`.
            //    We store the token — not the full URL — so the dispatcher can
            //    construct it dynamically.
            $htmlWithViewToken = $this->injectSnapshotToken($contentResult['html'], $snapshotViewToken);

            // 4. Create the send record using the enriched HTML.
            $sendRecord = $this->createSendRecord($newsletter, $validRecipients, array_merge(
                $contentResult,
                ['html' => $htmlWithViewToken]
            ));

            // 5. Create per-recipient rows (each has its own opaque token).
            $recipients = $this->recipientRepository->createRecipients($sendRecord->id, $validRecipients);

            // 6. Dispatch.  The dispatcher replaces {{VIEW_IN_BROWSER_URL}} per
            //    recipient by combining the snapshot token with the recipient token.
            $sendResult = $this->dispatcher->dispatch(
                $sendRecord,
                $recipients,
                $newsletter,
                $siteId,
                $htmlWithViewToken,
                false
            );

            // 7. Record last sent timestamp.
            $timestamp = new DateTimeImmutable();
            $this->newsletterRepository->update($newsletter->id, [
                'last_sent' => $timestamp->format('Y-m-d H:i:s'),
            ]);

            $stats = $this->recipientRepository->getStatistics($sendRecord->id);
            $success = $sendResult['success'] && $stats['failed'] === 0;

            return [
                'success' => $success,
                'newsletter_id' => $newsletter->id,
                'recipients' => $stats['sent'],
                'skipped' => $this->formatSkippedList($skipped),
                'failed' => $stats['failed'],
                'pending' => $stats['pending'],
                'pages_included' => $newsletter->isAutomated() ? count($contentResult['pages']) : 0,
                'send_id' => $sendRecord->id,
                'snapshot_id' => $snapshot->id,
                'partial_failure' => !$success && $stats['sent'] > 0,
            ];
        });
    }

    public function previewNewsletter(Newsletter $newsletter, array $previewEmails, ?int $siteId = null, ?Member $member = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();

        if (empty($previewEmails)) {
            return ['success' => false, 'error' => 'No preview email addresses provided'];
        }

        foreach ($previewEmails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'error' => "Invalid email address: {$email}"];
            }
        }

        if (count($previewEmails) > self::PREVIEW_RECIPIENT_LIMIT) {
            return ['success' => false, 'error' => 'Maximum ' . self::PREVIEW_RECIPIENT_LIMIT . ' preview recipients allowed'];
        }

        $contentResult = $this->contentBuilder->build($newsletter, $siteId, true, $member, true);
        if (!$contentResult['success']) {
            return $contentResult;
        }

        return $this->database->transaction(function () use ($newsletter, $previewEmails, $contentResult, $siteId) {
            $sendRecord = $this->createPreviewSendRecord($newsletter, $previewEmails, $contentResult);
            $recipients = $this->recipientRepository->createRecipients($sendRecord->id, $previewEmails);

            // Previews do not need view-in-browser; strip the placeholder.
            $previewHtml = str_replace(
                [NewsletterPageBuilderService::VIEW_IN_BROWSER_PLACEHOLDER, '{{UNSUBSCRIBE_LINK}}'],
                '',
                $contentResult['html']
            );

            $sendResult = $this->dispatcher->dispatch(
                $sendRecord,
                $recipients,
                $newsletter,
                $siteId,
                $previewHtml,
                true
            );

            $stats = $this->recipientRepository->getStatistics($sendRecord->id);

            return [
                'success' => $sendResult['success'],
                'newsletter_id' => $newsletter->id,
                'send_id' => $sendRecord->id,
                'message' => "Preview sent to {$stats['sent']} recipient(s)",
                'failed' => $stats['failed'],
                'preview' => true,
            ];
        });
    }

    public function retrySend(int $sendId, ?int $maxAttempts = 3, ?int $siteId = null): array
    {
        $send = $this->sendRepository->find($sendId);
        $siteId = $siteId ?? SiteContext::getId();

        if (!$send) {
            return ['success' => false, 'error' => 'Send record not found'];
        }

        if ($send->is_preview) {
            return ['success' => false, 'error' => 'Cannot retry preview sends'];
        }

        $newsletter = $this->newsletterRepository->find($send->newsletter_id);
        $retryableRecipients = $this->recipientRepository->getRetryableRecipients($sendId, $maxAttempts);

        if (empty($retryableRecipients)) {
            return ['success' => false, 'error' => 'No recipients available for retry'];
        }

        return $this->database->transaction(function () use ($send, $retryableRecipients, $newsletter, $siteId, $sendId) {
            $sendResult = $this->dispatcher->dispatch(
                $send,
                $retryableRecipients,
                $newsletter,
                $siteId,
                $send->html_snapshot,
                false
            );

            $stats = $this->recipientRepository->getStatistics($sendId);

            return [
                'success' => $sendResult['success'],
                'send_id' => $sendId,
                'retried' => count($retryableRecipients),
                'sent' => $stats['sent'],
                'failed' => $stats['failed'],
                'pending' => $stats['pending'],
            ];
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Embed the snapshot token as a data attribute on the VIEW_IN_BROWSER_URL
     * placeholder comment so the dispatcher can build the full per-recipient URL.
     *
     * The dispatcher replaces {{VIEW_IN_BROWSER_URL}} with:
     *   /newsletter/view/{snapshotToken}?r={recipientToken}
     *
     * We store the snapshot token in a separate, easily-replaceable placeholder
     * so the dispatcher can concatenate without re-parsing.
     */
    private function injectSnapshotToken(string $html, string $snapshotToken): string
    {
        // Replace the generic placeholder with a token-specific one that the
        // dispatcher can expand per-recipient.
        return str_replace(
            NewsletterPageBuilderService::VIEW_IN_BROWSER_PLACEHOLDER,
            '{{VIEW_IN_BROWSER_URL:' . $snapshotToken . '}}',
            $html
        );
    }

    private function isDuplicateSend(Newsletter $newsletter, int $siteId): bool
    {
        if (!$newsletter->last_sent) {
            return false;
        }

        $lastSent = DateTimeImmutable::createFromInterface($newsletter->last_sent);
        $now = new DateTimeImmutable();
        $diff = $now->getTimestamp() - $lastSent->getTimestamp();

        return $diff < 3600;
    }

    private function createSendRecord(Newsletter $newsletter, array $allSubscribers, array $contentResult): Model
    {
        $timestamp = DateTimeImmutable::createFromFormat('U', (string)time());

        return $this->sendRepository->create([
            'newsletter_id' => $newsletter->id,
            'sent_at' => $timestamp->format('Y-m-d H:i:s'),
            'recipient_count' => count($allSubscribers),
            'sent_count' => 0,
            'failed_count' => 0,
            'pending_count' => count($allSubscribers),
            'content_snapshot' => $contentResult['pages'],
            'html_snapshot' => $contentResult['html'],
        ]);
    }

    private function createPreviewSendRecord(Newsletter $newsletter, array $previewEmails, array $contentResult): Model
    {
        $timestamp = DateTimeImmutable::createFromFormat('U', (string)time());

        return $this->sendRepository->create([
            'newsletter_id' => $newsletter->id,
            'sent_at' => $timestamp->format('Y-m-d H:i:s'),
            'recipient_count' => count($previewEmails),
            'sent_count' => 0,
            'failed_count' => 0,
            'pending_count' => count($previewEmails),
            'content_snapshot' => $contentResult['pages'],
            'html_snapshot' => $contentResult['html'],
            'is_preview' => true,
        ]);
    }

    private function formatSkippedList(array $skippedAssoc): array
    {
        $formatted = [];
        foreach ($skippedAssoc as $email => $reason) {
            $formatted[] = ['email' => $email, 'reason' => $reason];
        }
        return $formatted;
    }

    public function sendToCustomEmails(
        Newsletter $newsletter,
        array      $customEmails,
        ?int       $siteId = null,
        ?Member    $actor = null,
    ): array
    {
        $siteId = $siteId ?? SiteContext::getId();

        if (empty($customEmails)) {
            return ['success' => false, 'error' => 'No recipient email addresses provided'];
        }

        foreach ($customEmails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'error' => "Invalid email address: {$email}"];
            }
        }

        $contentResult = $this->contentBuilder->build($newsletter, $siteId, false, $actor, true);
        if (!$contentResult['success']) {
            return $contentResult;
        }

        return $this->database->transaction(function () use ($newsletter, $customEmails, $contentResult, $siteId) {
            $snapshot = $this->snapshotRepository->createSnapshot(
                newsletterId: $newsletter->id,
                htmlSnapshot: $contentResult['html'],
                brandingSnapshot: null,
                layoutVersionId: null,
                brandingVersionId: null,
            );

            $snapshotViewToken = $this->viewTokenService->generateTokenForSnapshot($snapshot->id);
            $htmlWithViewToken = $this->injectSnapshotToken($contentResult['html'], $snapshotViewToken);

            $sendRecord = $this->sendRepository->create([
                'newsletter_id' => $newsletter->id,
                'sent_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'recipient_count' => count($customEmails),
                'sent_count' => 0,
                'failed_count' => 0,
                'pending_count' => count($customEmails),
                'content_snapshot' => $contentResult['pages'],
                'html_snapshot' => $htmlWithViewToken,
            ]);

            $recipients = $this->recipientRepository->createRecipients($sendRecord->id, $customEmails);

            $sendResult = $this->dispatcher->dispatch(
                $sendRecord,
                $recipients,
                $newsletter,
                $siteId,
                $htmlWithViewToken,
                false,
            );

            $stats = $this->recipientRepository->getStatistics($sendRecord->id);
            $success = $sendResult['success'] && $stats['failed'] === 0;

            return [
                'success' => $success,
                'newsletter_id' => $newsletter->id,
                'recipients' => $stats['sent'],
                'failed' => $stats['failed'],
                'pending' => $stats['pending'],
                'send_id' => $sendRecord->id,
                'snapshot_id' => $snapshot->id,
                'partial_failure' => !$success && $stats['sent'] > 0,
            ];
        });
    }
}