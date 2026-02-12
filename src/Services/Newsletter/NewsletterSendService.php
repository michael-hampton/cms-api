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
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Services\Cms\Pages\BlockParserService;
use App\Services\Members\EmailService;
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
        private readonly Database                          $database
    )
    {
    }


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

        // Check for duplicate send (idempotency)
        if ($this->isDuplicateSend($newsletter, $siteId)) {
            Logger::warning('Duplicate send attempt prevented', [
                'newsletter_id' => $newsletter->id
            ]);
            return [
                'success' => false,
                'newsletter_id' => $newsletter->id,
                'error' => 'Newsletter already sent recently'
            ];
        }

        // Build newsletter content
        $contentResult = $this->contentBuilder->build($newsletter, $siteId, false, $member);
        if (!$contentResult['success']) {
            return $contentResult;
        }

        // Resolve recipients with eligibility + preferences
        $resolutionResult = $this->recipientResolver->resolveForNewsletter($newsletter, $siteId);
        $validRecipients = $resolutionResult['valid'];
        $skipped = $resolutionResult['skipped'];

        if (empty($validRecipients)) {
            Logger::warning('No eligible recipients after filtering', [
                'newsletter_id' => $newsletter->id,
                'skipped_count' => count($skipped)
            ]);
            return [
                'success' => false,
                'newsletter_id' => $newsletter->id,
                'error' => 'No eligible recipients',
                'skipped' => $this->formatSkippedList($skipped)
            ];
        }

        return $this->database->transaction(function () use ($newsletter, $validRecipients, $contentResult, $siteId, $skipped) {
            // Create send record
            $sendRecord = $this->createSendRecord($newsletter, $validRecipients, $contentResult);

            // Create recipient records
            $recipients = $this->recipientRepository->createRecipients(
                $sendRecord->id,
                $validRecipients
            );

            // Dispatch send
            $sendResult = $this->dispatcher->dispatch(
                $sendRecord,
                $recipients,
                $newsletter,
                $siteId,
                $contentResult['html'],
                false
            );

            // Update newsletter last_sent
            $timestamp = new \DateTimeImmutable();
            $this->newsletterRepository->update($newsletter->id, [
                'last_sent' => $timestamp->format('Y-m-d H:i:s')
            ]);

            // Get final statistics
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
                'partial_failure' => !$success && $stats['sent'] > 0
            ];
        });
    }

    /**
     * Format skipped list from associative array
     */
    private function formatSkippedList(array $skippedAssoc): array
    {
        $formatted = [];
        foreach ($skippedAssoc as $email => $reason) {
            $formatted[] = [
                'email' => $email,
                'reason' => $reason
            ];
        }
        return $formatted;
    }

    public function previewNewsletter(Newsletter $newsletter, array $previewEmails, ?int $siteId = null, ?Member $member = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();

        if (empty($previewEmails)) {
            return [
                'success' => false,
                'error' => 'No preview email addresses provided'
            ];
        }

        // Validate all emails
        foreach ($previewEmails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'error' => "Invalid email address: {$email}"
                ];
            }
        }

        // Limit preview recipients
        if (count($previewEmails) > self::PREVIEW_RECIPIENT_LIMIT) {
            return [
                'success' => false,
                'error' => 'Maximum ' . self::PREVIEW_RECIPIENT_LIMIT . ' preview recipients allowed'
            ];
        }

        // Build newsletter content
        $contentResult = $this->contentBuilder->build($newsletter, $siteId, true, $member);
        if (!$contentResult['success']) {
            return $contentResult;
        }

        return $this->database->transaction(function () use ($newsletter, $previewEmails, $contentResult, $siteId) {
            // Create preview send record
            $sendRecord = $this->createPreviewSendRecord($newsletter, $previewEmails, $contentResult);

            // Create recipient records (preview tokens generated in-memory)
            $recipients = $this->recipientRepository->createRecipients($sendRecord->id, $previewEmails);

            $contentResult['html'] = str_replace('{{UNSUBSCRIBE_LINK}}', '', $contentResult['html']);

            // Dispatch preview send
            $sendResult = $this->dispatcher->dispatch(
                $sendRecord,
                $recipients,
                $newsletter,
                $siteId,
                $contentResult['html'],
                true
            );

            // Get final statistics
            $stats = $this->recipientRepository->getStatistics($sendRecord->id);

            return [
                'success' => $sendResult['success'],
                'newsletter_id' => $newsletter->id,
                'send_id' => $sendRecord->id,
                'message' => "Preview sent to {$stats['sent']} recipient(s)",
                'failed' => $stats['failed'],
                'preview' => true
            ];
        });
    }


    public function retrySend(int $sendId, ?int $maxAttempts = 3, ?int $siteId = null): array
    {
        $send = $this->sendRepository->find($sendId);
        $siteId = $siteId ?? SiteContext::getId();

        if (!$send) {
            return [
                'success' => false,
                'error' => 'Send record not found'
            ];
        }

        if ($send->is_preview) {
            return [
                'success' => false,
                'error' => 'Cannot retry preview sends'
            ];
        }

        $newsletter = $this->newsletterRepository->find($send->newsletter_id);

        // Get retryable recipients (excludes already-sent)
        $retryableRecipients = $this->recipientRepository->getRetryableRecipients($sendId, $maxAttempts);

        if (empty($retryableRecipients)) {
            return [
                'success' => false,
                'error' => 'No recipients available for retry'
            ];
        }

        return $this->database->transaction(function () use ($send, $retryableRecipients, $newsletter, $siteId, $sendId) {
            // Dispatch retry
            $sendResult = $this->dispatcher->dispatch(
                $send,
                $retryableRecipients,
                $newsletter,
                $siteId,
                $send->html_snapshot,
                false
            );

            // Get updated statistics
            $stats = $this->recipientRepository->getStatistics($sendId);

            return [
                'success' => $sendResult['success'],
                'send_id' => $sendId,
                'retried' => count($retryableRecipients),
                'sent' => $stats['sent'],
                'failed' => $stats['failed'],
                'pending' => $stats['pending']
            ];
        });
    }

    private function isDuplicateSend(Newsletter $newsletter, int $siteId): bool
    {
        // Check if newsletter was sent in last hour (prevents accidental double-sends)
        if (!$newsletter->last_sent) {
            return false;
        }

        $lastSent = \DateTimeImmutable::createFromInterface($newsletter->last_sent);
        $now = new DateTimeImmutable();
        $diff = $now->getTimestamp() - $lastSent->getTimestamp();

        return $diff < 3600; // 1 hour
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
            'html_snapshot' => $contentResult['html']
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
            'is_preview' => true
        ]);
    }

    private function buildSkippedList(array $allEmails, array $validEmails, array $skippedReasons = []): array
    {
        $skipped = [];
        foreach ($allEmails as $email) {
            if (!in_array($email, $validEmails)) {
                $reason = $skippedReasons[$email] ?? 'Unknown reason';
                $skipped[] = [
                    'email' => $email,
                    'reason' => $reason
                ];
            }
        }
        return $skipped;
    }
}