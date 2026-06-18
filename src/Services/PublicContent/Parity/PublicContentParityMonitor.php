<?php

namespace App\Services\PublicContent\Parity;

use App\DTO\PublicContent\ContentRegion;
use App\DTO\PublicContent\PublicContentDocument;
use App\Models\Member;
use App\Models\Page;
use App\Services\Cms\Pages\PageRenderService;
use Psr\Log\LoggerInterface;
use Throwable;

final class PublicContentParityMonitor
{
    public function __construct(
        private readonly PageRenderService $legacyRenderer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function compare(
        Page $page,
        int $siteId,
        ?Member $member,
        PublicContentDocument $v2,
    ): void {
        if (!$this->enabled() || !$this->sampled()) {
            return;
        }

        try {
            $legacy = $this->legacySnapshot($page, $siteId, $member);
            $differences = $this->differences($legacy, $v2);

            if ($differences === []) {
                return;
            }

            $this->logger->warning('Public content V1/V2 parity mismatch.', [
                'site_id' => $siteId,
                'page_id' => (int) $page->id,
                'slug' => (string) $page->slug,
                'viewer' => $member ? 'member' : 'guest',
                'differences' => $differences,
            ]);
        } catch (Throwable $exception) {
            $this->logger->warning('Public content parity comparison failed.', [
                'site_id' => $siteId,
                'page_id' => (int) $page->id,
                'slug' => (string) $page->slug,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function enabled(): bool
    {
        return filter_var(
            getenv('PUBLIC_CONTENT_PARITY_ENABLED') ?: false,
            FILTER_VALIDATE_BOOL,
        );
    }

    private function sampled(): bool
    {
        $percentage = (int) (getenv('PUBLIC_CONTENT_PARITY_SAMPLE_PERCENT') ?: 1);
        $percentage = max(0, min(100, $percentage));

        return $percentage > 0 && random_int(1, 100) <= $percentage;
    }

    private function legacySnapshot(Page $page, int $siteId, ?Member $member): array
    {
        $rendered = $this->legacyRenderer->renderPage($page, $siteId, $member);

        return [
            'title' => (string) $page->title,
            'type' => (string) $page->page_type,
            'summary' => $page->meta_description ?: null,
            'main_html' => $this->normaliseHtml((string) ($rendered['main'] ?? '')),
            'sidebar_html' => $this->normaliseHtml((string) ($rendered['sidebar'] ?? '')),
            'category_ids' => $page->categories?->map(
                static fn ($category): int => (int) $category->id,
            )->values()->toArray() ?? [],
            'tag_ids' => $page->tags?->map(
                static fn ($tag): int => (int) $tag->id,
            )->values()->toArray() ?? [],
            'author_ids' => $page->authors?->map(
                static fn ($author): int => (int) $author->id,
            )->values()->toArray() ?? [],
        ];
    }

    private function differences(array $legacy, PublicContentDocument $v2): array
    {
        $differences = [];
        $checks = [
            'title' => [$legacy['title'], $v2->title],
            'type' => [$legacy['type'], $v2->type],
            'summary' => [$legacy['summary'], $v2->summary],
            'main_html' => [$legacy['main_html'], $this->regionHtml($v2, 'main')],
            'sidebar_html' => [$legacy['sidebar_html'], $this->regionHtml($v2, 'sidebar')],
            'category_ids' => [$legacy['category_ids'], array_column($v2->taxonomy['categories'] ?? [], 'id')],
            'tag_ids' => [$legacy['tag_ids'], array_column($v2->taxonomy['tags'] ?? [], 'id')],
            'author_ids' => [$legacy['author_ids'], array_column($v2->authors, 'id')],
        ];

        foreach ($checks as $field => [$v1Value, $v2Value]) {
            if ($v1Value === $v2Value) {
                continue;
            }

            $differences[$field] = [
                'v1' => $this->summarise($v1Value),
                'v2' => $this->summarise($v2Value),
            ];
        }

        return $differences;
    }

    private function regionHtml(PublicContentDocument $document, string $name): string
    {
        $region = $document->regions[$name] ?? null;

        if ($region instanceof ContentRegion) {
            return $this->normaliseHtml($region->renderedHtml);
        }

        return '';
    }

    private function normaliseHtml(string $html): string
    {
        $html = preg_replace('/\s+/', ' ', trim($html)) ?? trim($html);

        return preg_replace('/>\s+</', '><', $html) ?? $html;
    }

    private function summarise(mixed $value): mixed
    {
        if (!is_string($value) || strlen($value) <= 500) {
            return $value;
        }

        return [
            'length' => strlen($value),
            'sha256' => hash('sha256', $value),
        ];
    }
}
