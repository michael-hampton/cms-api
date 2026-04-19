<?php

namespace App\Services\Newsletter;

use App\Framework\Support\Logger;
use App\Models\Newsletter;
use App\Models\NewsletterSend;
use App\Repositories\Newsletters\NewsletterSendRecipientRepository;
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Services\MemberInsights\EmailService;

class NewsletterDispatcher
{
    private const UNSUBSCRIBE_PLACEHOLDER = '{{UNSUBSCRIBE_LINK}}';
    private const BATCH_SIZE = 100;
    private const PREVIEW_TOKEN_SEED = 'newsletter-preview-v1';

    public function __construct(
        private readonly EmailService                           $emailService,
        private readonly NewsletterSendRecipientRepository      $recipientRepository,
        private readonly MemberSubscriptionPreferenceRepository $preferenceRepository,
        private readonly SubscriberRepository                   $subscriberRepository
    )
    {
    }

    public function dispatch(
        NewsletterSend $sendRecord,
        array          $recipients,
        Newsletter     $newsletter,
        int            $siteId,
        string         $baseHtml,
        bool           $isPreview
    ): array
    {
        if (empty($recipients)) {
            Logger::warning('Empty recipient list for send', [
                'send_id' => $sendRecord->id,
                'is_preview' => $isPreview
            ]);
            return ['success' => true, 'sent' => 0, 'failed' => 0];
        }

        // Bulk fetch unsubscribe tokens
        $emailsToProcess = array_map(fn($r) => is_array($r) ? $r['email'] : $r->email, $recipients);
        $unsubscribeTokens = $isPreview
            ? $this->generatePreviewTokens($emailsToProcess)
            : $this->bulkGetUnsubscribeTokens($emailsToProcess, $siteId);

        $sentCount = 0;
        $failedCount = 0;

        // Process in batches
        $batches = array_chunk($recipients, self::BATCH_SIZE);

        foreach ($batches as $batch) {
            foreach ($batch as $recipient) {
                try {
                    $recipientModel = is_array($recipient)
                        ? $this->recipientRepository->find($recipient['id'])
                        : $recipient;

//                    echo '<pre>';
//                    print_r(getType($recipientModel));
//                    die;

                    // Get unsubscribe token
                    $unsubscribeToken = $unsubscribeTokens[$recipientModel->email] ?? null;

                    // Only persist real tokens (not preview tokens)
                    if (!$isPreview && $unsubscribeToken && !$recipientModel->unsubscribe_token) {
                        $recipientModel->update(['unsubscribe_token' => $unsubscribeToken]);
                    }

                    // Personalize HTML
                    $html = $this->personalizeHtmlForRecipient(
                        $baseHtml,
                        $recipientModel->email,
                        $unsubscribeToken,
                        $sendRecord->id,
                        $newsletter->isAutomated(),
                        $recipientModel->view_token,
                    );

                    // Build subject
                    $subject = $isPreview ? '[PREVIEW] ' . $newsletter->title : $newsletter->title;

                    // Send email
                    $this->emailService->send($recipientModel->email, $subject, $html);

                    // Mark as sent
                    $recipientModel->markAsSent();
                    $sentCount++;

                } catch (\Exception $e) {
                    // Mark as failed
                    $recipientModel->markAsFailed($e->getMessage());
                    $failedCount++;

                    Logger::error("Failed to send newsletter", [
                        'email' => $recipientModel->email,
                        'send_id' => $sendRecord->id,
                        'is_preview' => $isPreview,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Update send counts
        $this->recipientRepository->updateSendCounts($sendRecord->id);

        return [
            'success' => $failedCount === 0,
            'sent' => $sentCount,
            'failed' => $failedCount
        ];
    }

    private function bulkGetUnsubscribeTokens(array $emails, int $siteId): array
    {
        $tokens = [];

        // Get member preferences
        $preferences = $this->preferenceRepository->findByEmails($emails, $siteId);
        foreach ($preferences as $pref) {
            if ($pref->unsubscribe_token) {
                $tokens[$pref->member->email] = $pref->unsubscribe_token;
            }
        }

        // Get legacy subscriber tokens
        $remainingEmails = array_values(array_diff($emails, array_keys($tokens)));
        if (!empty($remainingEmails)) {
            $subscribers = $this->subscriberRepository->findByEmails($remainingEmails, $siteId);
            foreach ($subscribers as $subscriber) {
                if ($subscriber->unsubscribe_token) {
                    $tokens[$subscriber->email] = $subscriber->unsubscribe_token;
                }
            }
        }

        return $tokens;
    }

    private function generatePreviewTokens(array $emails): array
    {
        $tokens = [];
        foreach ($emails as $email) {
            // Deterministic preview tokens for testing
            $tokens[$email] = hash('sha256', self::PREVIEW_TOKEN_SEED . $email);
        }
        return $tokens;
    }

    private function personalizeHtmlForRecipient(
        string  $html,
        string  $email,
        ?string $unsubscribeToken,
        int     $sendId,
        bool    $isAutomated,
        ?string $recipientToken = null,
    ): string
    {
        // Replace the per-snapshot view-in-browser placeholder with the
        // per-recipient URL: /newsletter/view/{snapshotToken}?r={recipientToken}
        $html = preg_replace_callback(
            '/\{\{VIEW_IN_BROWSER_URL:([a-f0-9]+)\}\}/',
            function (array $matches) use ($recipientToken): string {
                $snapshotToken = $matches[1];
                $url = url("/newsletter/view/{$snapshotToken}");
                if ($recipientToken) {
                    $url .= '?r=' . urlencode($recipientToken);
                }
                return $url;
            },
            $html
        );

        if ($isAutomated) {
            // Replace tracking placeholders
            $emailHash = hash('sha256', $email);
            $html = str_replace('{{TRACKING_EMAIL}}', $emailHash, $html);
            $html = str_replace('{{SEND_ID}}', (string)$sendId, $html);
        }

        // Inject unsubscribe link exactly once
        if ($unsubscribeToken) {
            $unsubscribeUrl = url("/member/subscriptions/unsubscribe/{$unsubscribeToken}");
            $unsubscribeFooter = $this->buildUnsubscribeFooter($unsubscribeUrl);
            $html = str_replace(self::UNSUBSCRIBE_PLACEHOLDER, $unsubscribeFooter, $html);
        } else {
            $html = str_replace(self::UNSUBSCRIBE_PLACEHOLDER, '', $html);
        }

        return $html;
    }

    private function buildUnsubscribeFooter(string $unsubscribeUrl): string
    {
        return <<<HTML
<hr style="border: none; border-top: 1px solid #e0e0e0; margin: 20px 0;">
<p style="font-size: 12px; color: #666; text-align: center;">
    Don't want to receive these emails? <a href="{$unsubscribeUrl}" style="color: #007bff; text-decoration: none;">Unsubscribe</a>
</p>
HTML;
    }
}