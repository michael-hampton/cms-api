<?php
namespace App\Services\Newsletter;

use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendRecipientRepository;
use App\Repositories\Newsletters\NewsletterSendRepository;
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Services\Cms\Pages\BlockParserService;
use App\Services\Members\EmailService;
use App\Services\Subscriptions\MemberSubscriptionService;

class NewsletterSendService
{
    public function __construct(
        private readonly BlockParserService                     $parser,
        private readonly EmailService                           $emailService,
        private readonly SubscriberRepository                   $subscriberRepository,
        private readonly NewsletterRepository                   $newsletterRepository,
        private readonly NewsletterSendRepository               $sendRepository,
        private readonly MemberSubscriptionPreferenceRepository $preferenceRepository,
        private readonly NewsletterPageBuilderService $pageBuilderService,
        private readonly MemberRepository             $memberRepository,
        private readonly MemberSubscriptionService         $subscriptionService,
        private readonly NewsletterSendRecipientRepository $recipientRepository
    )
    {
    }

    public function sendDueNewsletters(?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();
        $newsletters = $this->newsletterRepository->getDueNewsletters($siteId);
        $results = [];

        foreach ($newsletters as $newsletter) {
            $results[] = $this->sendNewsletter($newsletter, $siteId);
        }

        return $results;
    }

    public function sendNewsletter($newsletter, ?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();

        // Get all confirmed subscribers
        $legacySubscribers = $this->subscriberRepository->getConfirmedEmails($siteId);
        $memberPreferences = $this->preferenceRepository->getActiveSubscribersForSite($siteId);

        $filteredMembers = $memberPreferences->filter(function ($pref) use ($newsletter) {
            return $pref->newsletter_frequency === $newsletter->interval;
        });

        $memberEmails = $filteredMembers->map(function ($pref) {
            return $pref->member->email;
        })->toArray();

        $allSubscribers = array_unique(array_merge($legacySubscribers, $memberEmails));

        if (empty($allSubscribers)) {
            return [
                'success' => false,
                'newsletter_id' => $newsletter->id,
                'error' => 'No confirmed subscribers'
            ];
        }

        $pages = [];
        $baseHtml = '';

        // Build newsletter content
        if ($newsletter->isAutomated()) {
            $pages = $this->pageBuilderService->getPagesForNewsletter($newsletter, $siteId);

            if ($pages->isEmpty()) {
                return [
                    'success' => false,
                    'newsletter_id' => $newsletter->id,
                    'error' => 'No pages match newsletter criteria'
                ];
            }

            $pages = $pages->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'subtitle' => $p->subtitle,
                'slug' => $p->slug,
            ])->toArray();
        } else {
            $blocks = json_decode($newsletter->content, true);
            if (!is_array($blocks)) {
                $blocks = [];
            }
            $baseHtml = $this->renderBlocksToHtml($blocks);
        }

        // Create send record
        $sendRecord = $this->sendRepository->create([
            'newsletter_id' => $newsletter->id,
            'sent_at' => date('Y-m-d H:i:s'),
            'recipient_count' => count($allSubscribers),
            'sent_count' => 0,
            'failed_count' => 0,
            'pending_count' => count($allSubscribers),
            'content_snapshot' => $pages,
            'html_snapshot' => null
        ]);

        $sendId = $sendRecord->id;

        // Generate base HTML
        if ($newsletter->isAutomated()) {
            $pagesCollection = collect($pages);
            $baseHtml = $this->pageBuilderService->buildNewsletterHtml(
                $newsletter,
                $pagesCollection,
                null,
                false,
                $sendId
            );
        }

        // Store HTML snapshot
        $this->sendRepository->update($sendId, [
            'html_snapshot' => $baseHtml
        ]);

        // Filter and create recipient records
        $validRecipients = $this->filterRecipients($allSubscribers, $newsletter, $siteId);

        $skipped = [];
        foreach ($allSubscribers as $email) {
            if (!in_array($email, $validRecipients['valid'])) {
                $reason = $validRecipients['skipped'][$email] ?? 'Unknown reason';
                $skipped[] = [
                    'email' => $email,
                    'reason' => $reason
                ];
            }
        }

        // Create recipient records
        $recipients = $this->recipientRepository->createRecipients($sendId, $validRecipients['valid']);

        // Send to all recipients
        $this->processSendQueue($sendRecord, $recipients, $newsletter, $siteId, $baseHtml);

        // Update newsletter last_sent
        $this->newsletterRepository->update($newsletter->id, ['last_sent' => date('Y-m-d H:i:s')]);

        // Get final statistics
        $stats = $this->recipientRepository->getStatistics($sendId);

        return [
            'success' => true,
            'newsletter_id' => $newsletter->id,
            'recipients' => $stats['sent'],
            'skipped' => $skipped,
            'failed' => $stats['failed'],
            'pending' => $stats['pending'],
            'pages_included' => $newsletter->isAutomated() ? count($pages) : 0,
            'send_id' => $sendId
        ];
    }

    public function previewNewsletter($newsletter, array $previewEmails, ?int $siteId = null): array
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

        // Limit to 10 preview recipients
        if (count($previewEmails) > 10) {
            return [
                'success' => false,
                'error' => 'Maximum 10 preview recipients allowed'
            ];
        }

        $formattedPages = [];
        $baseHtml = '';
        $pages = null;

        // Build newsletter content
        if ($newsletter->isAutomated()) {
            $pages = $this->pageBuilderService->getPagesForNewsletter($newsletter, $siteId);

            if ($pages->isEmpty()) {
                return [
                    'success' => false,
                    'error' => 'No pages match newsletter criteria'
                ];
            }

            $formattedPages = $pages->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'subtitle' => $p->subtitle,
                'slug' => $p->slug,
            ])->toArray();
        } else {
            $blocks = json_decode($newsletter->content, true);
            $blocks = is_array($blocks) ? $blocks : [];
            $baseHtml = $this->renderBlocksToHtml($blocks);
        }

        // Create preview send record
        $sendRecord = $this->sendRepository->create([
            'newsletter_id' => $newsletter->id,
            'sent_at' => date('Y-m-d H:i:s'),
            'recipient_count' => count($previewEmails),
            'sent_count' => 0,
            'failed_count' => 0,
            'pending_count' => count($previewEmails),
            'content_snapshot' => $formattedPages,
            'html_snapshot' => null
        ]);

        $sendId = $sendRecord->id;

        // Generate base HTML
        if ($newsletter->isAutomated() && $pages instanceof Collection) {
            $baseHtml = $this->pageBuilderService->buildNewsletterHtml(
                $newsletter,
                $pages,
                null,
                false,
                $sendId
            );
        }

        // Add preview notice
        $previewNotice = '<div style="background: #fef3c7; border: 2px solid #f59e0b; padding: 15px; margin-bottom: 20px; border-radius: 4px; text-align: center;"><strong>⚠️ PREVIEW:</strong> This is a preview of the newsletter. Unsubscribe links will not work.</div>';
        $baseHtml = preg_replace('/(<body[^>]*>)/i', '$1' . $previewNotice, $baseHtml);

        // Store HTML
        $this->sendRepository->update($sendId, [
            'html_snapshot' => $baseHtml
        ]);

        // Create recipient records for preview
        $recipients = $this->recipientRepository->createRecipients($sendId, $previewEmails);

        // Send previews
        $this->processSendQueue($sendRecord, $recipients, $newsletter, $siteId, $baseHtml, true);

        // Get statistics
        $stats = $this->recipientRepository->getStatistics($sendId);

        return [
            'success' => true,
            'preview' => true,
            'newsletter_id' => $newsletter->id,
            'recipients' => $stats['sent'],
            'failed' => $stats['failed'],
            'pages_included' => $newsletter->isAutomated() ? count($pages) : 0,
            'send_id' => $sendId,
            'message' => "Preview sent to {$stats['sent']} recipient(s)"
        ];
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

        $newsletter = $this->newsletterRepository->find($send->newsletter_id);
        $baseHtml = $send->html_snapshot;

        // Get retryable recipients
        $retryableRecipients = $this->recipientRepository->getRetryableRecipients($sendId, $maxAttempts);

        if (empty($retryableRecipients)) {
            return [
                'success' => false,
                'error' => 'No recipients available for retry'
            ];
        }

        // Process retry queue
        $this->processSendQueue($send, $retryableRecipients, $newsletter, $siteId, $baseHtml);

        // Get updated statistics
        $stats = $this->recipientRepository->getStatistics($sendId);

        return [
            'success' => true,
            'send_id' => $sendId,
            'retried' => count($retryableRecipients),
            'sent' => $stats['sent'],
            'failed' => $stats['failed'],
            'pending' => $stats['pending']
        ];
    }

    private function filterRecipients(array $emails, $newsletter, int $siteId): array
    {
        $valid = [];
        $skipped = [];

        foreach ($emails as $email) {
            $member = $this->memberRepository->findByEmail($email);

            if ($member) {
                // Check global newsletter preference
                if (!$member->getCommunicationPreference('newsletter', true)) {
                    $skipped[$email] = 'Newsletter preference disabled in global settings';
                    continue;
                }

                // Check global marketing preference
                if (!$member->wantsMarketingEmails()) {
                    $skipped[$email] = 'Marketing emails disabled in global settings';
                    continue;
                }
            }

            $valid[] = $email;
        }

        return [
            'valid' => $valid,
            'skipped' => $skipped
        ];
    }

    private function processSendQueue(
        $sendRecord,
        array $recipients,
        $newsletter,
        int $siteId,
        string $baseHtml,
        bool $isPreview = false): void
    {
        foreach ($recipients as $recipient) {
            try {

                // Store token in recipient record
                $recipientModel = is_array($recipient)
                    ? $this->recipientRepository->find($recipient['id'])
                    : $recipient;

                // Get unsubscribe token
                $unsubscribeToken = $isPreview
                    ? 'preview-' . bin2hex(random_bytes(16))
                    : $this->getUnsubscribeToken($recipientModel->email, $siteId);

                $recipientModel->update(['unsubscribe_token' => $unsubscribeToken]);

                // Personalize HTML
                $html = $newsletter->isAutomated()
                    ? $this->personalizeHtmlForRecipient($baseHtml, $recipientModel->email, $unsubscribeToken, $sendRecord->id)
                    : $this->addUnsubscribeLink($baseHtml, $unsubscribeToken);

                // Add preview prefix if preview
                $subject = $isPreview ? '[PREVIEW] ' . $newsletter->title : $newsletter->title;

                // Send email
                $this->emailService->send($recipientModel->email, $subject, $html);

                // Mark as sent
                $recipientModel->markAsSent();

            } catch (\Exception $e) {
                // Mark as failed
                $recipientModel->markAsFailed($e->getMessage());

                error_log("Failed to send to {$recipientModel->email}: " . $e->getMessage());
            }
        }

        // Update send counts
        $this->recipientRepository->updateSendCounts($sendRecord->id);
    }

    private function getUnsubscribeToken(string $email, int $siteId): ?string
    {
        // First check if it's a member
        $preference = $this->preferenceRepository->findByMemberEmail($email, $siteId);
        if ($preference) {
            return $preference->unsubscribe_token;
        }

        // Fall back to legacy subscriber
        $subscriber = $this->subscriberRepository->findByEmail($email, $siteId);
        return $subscriber?->unsubscribe_token;
    }

    /**
     * Personalize HTML for a specific recipient with tracking links
     */
    private function personalizeHtmlForRecipient(string $html, string $email, ?string $unsubscribeToken, int $sendId): string
    {
        $html = urldecode($html);

        // Replace tracking placeholders with recipient-specific values
        $emailHash = md5($email);

        // Replace {{TRACKING_EMAIL}} placeholder with hashed email
        $html = str_replace('{{TRACKING_EMAIL}}', $emailHash, $html);

        // Replace {{SEND_ID}} placeholder
        $html = str_replace('{{SEND_ID}}', (string)$sendId, $html);

        // Add unsubscribe footer
        if ($unsubscribeToken) {
            $unsubscribeUrl = url("/member/subscriptions/unsubscribe/{$unsubscribeToken}");
            $unsubscribeFooter = "<hr><p style='font-size: 12px; color: #666;'>Don't want to receive these emails? <a href='{$unsubscribeUrl}'>Unsubscribe</a></p>";
            $html .= $unsubscribeFooter;
        }

        return $html;
    }

    private function addUnsubscribeLink(string $html, ?string $token): string
    {
        if (!$token) {
            return $html;
        }

        $unsubscribeUrl = url("/member/subscriptions/unsubscribe/{$token}");
        $unsubscribeFooter = "<hr><p style='font-size: 12px; color: #666;'>Don't want to receive these emails? <a href='{$unsubscribeUrl}'>Unsubscribe</a></p>";

        return $html . $unsubscribeFooter;
    }

    private function renderBlocksToHtml(array $blocks): string
    {
        $html = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? 'paragraph';
            $content = $block['content'] ?? '';

            switch ($type) {
                case 'heading':
                    $level = $block['level'] ?? 2;
                    $html[] = "<h{$level}>" . htmlspecialchars($content) . "</h{$level}>";
                    break;

                case 'paragraph':
                    $html[] = "<p>" . htmlspecialchars($content) . "</p>";
                    break;

                case 'image':
                    $url = htmlspecialchars($block['url'] ?? '');
                    $alt = htmlspecialchars($block['alt'] ?? '');
                    $html[] = "<img src=\"{$url}\" alt=\"{$alt}\" style=\"max-width: 100%;\">";
                    break;

                case 'list':
                    $items = $block['items'] ?? [];
                    $listHtml = '<ul>';
                    foreach ($items as $item) {
                        $listHtml .= '<li>' . htmlspecialchars($item) . '</li>';
                    }
                    $listHtml .= '</ul>';
                    $html[] = $listHtml;
                    break;

                case 'button':
                    $url = htmlspecialchars($block['url'] ?? '#');
                    $text = htmlspecialchars($content);
                    $html[] = "<a href=\"{$url}\" style=\"display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;\">{$text}</a>";
                    break;

                default:
                    $html[] = "<div>" . htmlspecialchars($content) . "</div>";
            }
        }

        return implode("\n", $html);
    }
}