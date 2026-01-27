<?php
namespace App\Services\Newsletter;

use App\Framework\Support\SiteContext;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendRepository;
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Services\Cms\BlockParserService;
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
        private readonly MemberSubscriptionService    $subscriptionService
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

        // Get all confirmed subscribers (legacy system)
        $legacySubscribers = $this->subscriberRepository->getConfirmedEmails($siteId);

        // Get member subscribers with preferences
        $memberPreferences = $this->preferenceRepository->getActiveSubscribersForSite($siteId);

        // Filter member preferences by newsletter frequency
        $filteredMembers = $memberPreferences->filter(function ($pref) use ($newsletter) {
            return $pref->newsletter_frequency === $newsletter->interval;
        });

        // Get member emails
        $memberEmails = $filteredMembers->map(function ($pref) {
            return $pref->member->email;
        })->toArray();

        // Combine and deduplicate
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

            // Store page data for snapshot
            $pages = $pages->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'subtitle' => $p->subtitle,
                'slug' => $p->slug,
            ])->toArray();
        } else {
            // Manual newsletter - use blocks
            $blocks = json_decode($newsletter->content, true);
            if (!is_array($blocks)) {
                $blocks = [];
            }
            $baseHtml = $this->renderBlocksToHtml($blocks);
        }

        $sent = 0;
        $failed = [];
        $skipped = [];
        $successfulRecipients = [];

        // Log send with recipients and HTML snapshot - Create the send record BEFORE sending emails so we have a sendId
        $sendRecord = $this->sendRepository->create([
            'newsletter_id' => $newsletter->id,
            'sent_at' => date('Y-m-d H:i:s'),
            'recipient_count' => $sent,
            'content_snapshot' => $pages,
            'recipients' => $successfulRecipients,
            'html_snapshot' => null
        ]);

        $sendId = $sendRecord->id;

        // Now generate base HTML with tracking (for automated newsletters)
        if ($newsletter->isAutomated()) {
            // Rebuild pages collection from snapshot for rendering
            $pagesCollection = collect($pages);
            $baseHtml = $this->pageBuilderService->buildNewsletterHtml(
                $newsletter,
                $pagesCollection,
                null, // unsubscribe token - will be added per-recipient
                false, // includeBlocks
                $sendId // NEW: Pass sendId for tracking
            );
        } else {
            // Manual newsletter - use blocks
            $blocks = json_decode($newsletter->content, true);
            if (!is_array($blocks)) {
                $blocks = [];
            }
            $baseHtml = $this->renderBlocksToHtml($blocks);
        }

        foreach ($allSubscribers as $email) {
            try {
                $member = $this->memberRepository->findByEmail($email);

                if ($member) {
                    // NEW: Check global newsletter preference
                    if (!$member->getCommunicationPreference('newsletter', true)) {
                        $skipped[] = [
                            'email' => $email,
                            'reason' => 'Newsletter preference disabled in global settings'
                        ];
                        continue;
                    }

                    // NEW: Check global marketing preference
                    if (!$member->wantsMarketingEmails()) {
                        $skipped[] = [
                            'email' => $email,
                            'reason' => 'Marketing emails disabled in global settings'
                        ];
                        continue;
                    }

                    // Existing MemberSubscriptionPreference check
                    /* $preference = $this->preferenceRepository->findByMemberEmail($email, $siteId);
                     if ($preference && !$preference->is_active) {
                         $skipped[] = [
                             'email' => $email,
                             'reason' => 'Subscription preference inactive'
                         ];
                         continue;
                     }

                     // Check if member's preference matches newsletter frequency
                     if ($preference && $preference->newsletter_frequency !== $newsletter->interval) {
                         $skipped[] = [
                             'email' => $email,
                             'reason' => "Frequency mismatch: wants {$preference->newsletter_frequency}, newsletter is {$newsletter->interval}"
                         ];
                         continue;
                     }*/
                }

                // Get unsubscribe token for this email
                $unsubscribeToken = $this->getUnsubscribeToken($email, $siteId);

                // Add unsubscribe link to HTML
                $html = $newsletter->isAutomated()
                    ? $this->personalizeHtmlForRecipient($baseHtml, $email, $unsubscribeToken, $sendId)
                    : $this->addUnsubscribeLink($baseHtml, $unsubscribeToken);

                $this->emailService->send($email, $newsletter->title, $html);
                $sent++;
                $successfulRecipients[] = $email;
            } catch (\Exception $e) {
                $failed[] = [
                    'email' => $email,
                    'error' => $e->getMessage()
                ];
                error_log("Failed to send to {$email}: " . $e->getMessage());
            }
        }

        // Update newsletter
        $this->newsletterRepository->update($newsletter->id, ['last_sent' => date('Y-m-d H:i:s')]);

        // Update send record with final data
        $this->sendRepository->update($sendRecord->id, [
            'recipient_count' => $sent,
            'recipients' => $successfulRecipients,
            'html_snapshot' => $baseHtml // Store the base HTML without personalization
        ]);

        return [
            'success' => true,
            'newsletter_id' => $newsletter->id,
            'recipients' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
            'pages_included' => $newsletter->isAutomated() ? count($pages) : 0,
            'send_id' => $sendId
        ];
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