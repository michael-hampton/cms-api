<?php

namespace App\Services;

use App\Framework\Support\SiteContext;
use App\Repositories\NewsletterRepository;
use App\Repositories\NewsletterSendRepository;
use App\Repositories\SubscriberRepository;

class NewsletterSendService
{
    private BlockParserService $parser;
    private EmailService $emailService;
    private SubscriberRepository $subscriberRepository;
    private NewsletterRepository $newsletterRepository;
    private NewsletterSendRepository $sendRepository;
    private ?int $siteId;

    public function __construct(
        BlockParserService $parser,
        EmailService $emailService,
        SubscriberRepository $subscriberRepository,
        NewsletterRepository $newsletterRepository,
        NewsletterSendRepository $sendRepository,
    ) {
        $this->parser = $parser;
        $this->emailService = $emailService;
        $this->subscriberRepository = $subscriberRepository;
        $this->newsletterRepository = $newsletterRepository;
        $this->sendRepository = $sendRepository;
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
        $subscribers = $this->subscriberRepository->getConfirmedEmails($siteId);

        if (empty($subscribers)) {
            return [
                'success' => false,
                'newsletter_id' => $newsletter->id,
                'error' => 'No confirmed subscribers'
            ];
        }

        // Parse blocks using BlockParserService
        $blocks = json_decode($newsletter->content, true);
        if (!is_array($blocks)) {
            $blocks = [];
        }

        $html = $this->renderBlocksToHtml($blocks);

        $sent = 0;
        foreach ($subscribers as $email) {
            try {
                $this->emailService->send($email, $newsletter->title, $html);
                $sent++;
            } catch (\Exception $e) {
                error_log("Failed to send to {$email}: " . $e->getMessage());
            }
        }

        // Update newsletter
        $this->newsletterRepository->update($newsletter->id, ['last_sent' => date('Y-m-d H:i:s')]);

        // Log send
        $this->sendRepository->create([
            'newsletter_id' => $newsletter->id,
            'sent_at' => date('Y-m-d H:i:s'),
            'recipient_count' => $sent
        ]);

        return [
            'success' => true,
            'newsletter_id' => $newsletter->id,
            'recipients' => $sent
        ];
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
                    $html[] = "<img src=\"{$url}\" alt=\"{$alt}\">";
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
                    $html[] = "<a href=\"{$url}\" class=\"button\">{$text}</a>";
                    break;

                default:
                    $html[] = "<div>" . htmlspecialchars($content) . "</div>";
            }
        }

        return implode("\n", $html);
    }
}