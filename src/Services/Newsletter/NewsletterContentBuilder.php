<?php

namespace App\Services\Newsletter;

use App\Framework\Support\Logger;
use App\Models\Member;
use App\Models\Newsletter;

class NewsletterContentBuilder
{
    private const UNSUBSCRIBE_PLACEHOLDER = '{{UNSUBSCRIBE_LINK}}';

    public function __construct(
        private readonly NewsletterPageBuilderService $pageBuilderService
    )
    {
    }

    public function build(Newsletter $newsletter, int $siteId, bool $isPreview, ?Member $member = null): array
    {
        $pages = [];
        $baseHtml = '';

        if ($newsletter->isAutomated()) {
            $pagesCollection = $this->pageBuilderService->getPagesForNewsletter($newsletter, $siteId);

            if ($pagesCollection->isEmpty()) {
                return [
                    'success' => false,
                    'newsletter_id' => $newsletter->id,
                    'error' => 'No pages match newsletter criteria'
                ];
            }

            $pages = $pagesCollection->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'subtitle' => $p->subtitle,
                'slug' => $p->slug,
            ])->toArray();

            // Build HTML with placeholder
            $baseHtml = $this->pageBuilderService->buildNewsletterHtml(
                $newsletter,
                $pagesCollection,
                $member,
                null,
                $isPreview,
                null,
                $siteId
            );
        } else {
            $blocks = $this->parseNewsletterContent($newsletter->content);
            $baseHtml = $this->renderBlocksToHtml($blocks);
        }

        // Ensure unsubscribe placeholder exists
        if (!str_contains($baseHtml, self::UNSUBSCRIBE_PLACEHOLDER)) {
            $baseHtml .= "\n" . self::UNSUBSCRIBE_PLACEHOLDER;
        }

        return [
            'success' => true,
            'html' => $baseHtml,
            'pages' => $pages
        ];
    }

    private function parseNewsletterContent(string $content): array
    {
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::warning('Failed to decode newsletter content', [
                'json_error' => json_last_error_msg(),
                'content_length' => strlen($content)
            ]);
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function renderBlocksToHtml(array $blocks): string
    {
        $html = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? 'paragraph';
            $content = $block['content'] ?? '';

            $html[] = match ($type) {
                'heading' => $this->renderHeading($block),
                'paragraph' => $this->renderParagraph($content),
                'image' => $this->renderImage($block),
                'list' => $this->renderList($block),
                'button' => $this->renderButton($block),
                default => "<div>" . htmlspecialchars($content) . "</div>"
            };
        }

        return implode("\n", $html);
    }

    private function renderHeading(array $block): string
    {
        $level = $block['level'] ?? 2;
        $content = htmlspecialchars($block['content'] ?? '');
        return "<h{$level}>{$content}</h{$level}>";
    }

    private function renderParagraph(string $content): string
    {
        return "<p>" . htmlspecialchars($content) . "</p>";
    }

    private function renderImage(array $block): string
    {
        $url = htmlspecialchars($block['url'] ?? '');
        $alt = htmlspecialchars($block['alt'] ?? '');
        return "<img src=\"{$url}\" alt=\"{$alt}\" style=\"max-width: 100%;\">";
    }

    private function renderList(array $block): string
    {
        $items = $block['items'] ?? [];
        $html = '<ul>';
        foreach ($items as $item) {
            $html .= '<li>' . htmlspecialchars($item) . '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    private function renderButton(array $block): string
    {
        $url = htmlspecialchars($block['url'] ?? '#');
        $text = htmlspecialchars($block['content'] ?? '');
        return "<a href=\"{$url}\" style=\"display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;\">{$text}</a>";
    }
}