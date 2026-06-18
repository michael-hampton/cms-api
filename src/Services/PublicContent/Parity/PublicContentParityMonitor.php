<?php

namespace App\Services\PublicContent\Parity;

use App\DTO\PublicContent\ContentRegion;
use App\DTO\PublicContent\PublicContentDocument;
use App\Framework\Support\Logger;
use App\Models\Member;
use App\Models\Page;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\Cms\Pages\PageRenderService;
use Psr\Log\LoggerInterface;
use Throwable;

final class PublicContentParityMonitor
{
    private const int HTML_CONTEXT_LENGTH = 240;

    public function __construct(
        private readonly PublicContentPageRepository $pages,
        private readonly PageRenderService $legacyRenderer,
        private readonly PublicContentParityReportWriter $reportWriter,
        private readonly Logger $logger,
    ) {
    }

    public function compareDocument(PublicContentDocument $v2, ?Member $member): void
    {
        if (!$this->enabled() || !$this->sampled()) {
            return;
        }

        $startedAt = microtime(true);
        $baseRecord = [
            'schema_version' => 1,
            'recorded_at' => date(DATE_ATOM),
            'site_id' => $v2->siteId,
            'page_id' => $v2->id,
            'slug' => $v2->slug,
            'viewer' => $member ? 'member' : 'guest',
        ];

        try {
            $page = $this->pages->findCompletePublishedBySlug($v2->siteId, $v2->slug);

            if (!$page) {
                $this->writeReport($baseRecord + [
                    'status' => 'unresolved',
                    'duration_ms' => $this->durationMs($startedAt),
                    'differences' => [],
                    'error' => [
                        'message' => 'Published page could not be resolved for parity comparison.',
                    ],
                ]);
                return;
            }

            $legacy = $this->legacySnapshot($page, $v2->siteId, $member);
            $differences = $this->differences($legacy, $v2);

            if ($differences === [] && !$this->logMatches()) {
                return;
            }

            $this->writeReport($baseRecord + [
                'status' => $differences === [] ? 'matched' : 'mismatched',
                'duration_ms' => $this->durationMs($startedAt),
                'difference_count' => count($differences),
                'difference_fields' => array_keys($differences),
                'differences' => $differences,
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            $this->writeReport($baseRecord + [
                'status' => 'failed',
                'duration_ms' => $this->durationMs($startedAt),
                'differences' => [],
                'error' => [
                    'type' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            ]);
        }
    }

    private function writeReport(array $record): void
    {
        try {
            $this->reportWriter->append($record);
        } catch (Throwable $exception) {
            $this->logger->warning('Public content parity report could not be written.', [
                'path' => $this->reportWriter->path(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'record' => $record,
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

    private function logMatches(): bool
    {
        $value = getenv('PUBLIC_CONTENT_PARITY_LOG_MATCHES');

        return $value === false
            || filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function sampled(): bool
    {
        $percentage = (int) (getenv('PUBLIC_CONTENT_PARITY_SAMPLE_PERCENT') ?: 1);
        $percentage = max(0, min(100, $percentage));

        return $percentage > 0 && random_int(1, 100) <= $percentage;
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
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

            $differences[$field] = in_array($field, ['main_html', 'sidebar_html'], true)
                ? $this->htmlDifference((string) $v1Value, (string) $v2Value)
                : [
                    'v1' => $this->summarise($v1Value),
                    'v2' => $this->summarise($v2Value),
                ];
        }

        return $differences;
    }

    private function htmlDifference(string $v1, string $v2): array
    {
        $v1Length = strlen($v1);
        $v2Length = strlen($v2);
        $limit = min($v1Length, $v2Length);
        $firstDifference = 0;

        while ($firstDifference < $limit && $v1[$firstDifference] === $v2[$firstDifference]) {
            $firstDifference++;
        }

        $commonSuffixLength = 0;
        while (
            $commonSuffixLength < ($v1Length - $firstDifference)
            && $commonSuffixLength < ($v2Length - $firstDifference)
            && $v1[$v1Length - 1 - $commonSuffixLength] === $v2[$v2Length - 1 - $commonSuffixLength]
        ) {
            $commonSuffixLength++;
        }

        $v1ChangedLength = max(0, $v1Length - $firstDifference - $commonSuffixLength);
        $v2ChangedLength = max(0, $v2Length - $firstDifference - $commonSuffixLength);
        $contextStart = max(0, $firstDifference - self::HTML_CONTEXT_LENGTH);
        $contextLength = self::HTML_CONTEXT_LENGTH * 2;

        return [
            'v1' => [
                'length' => $v1Length,
                'sha256' => hash('sha256', $v1),
            ],
            'v2' => [
                'length' => $v2Length,
                'sha256' => hash('sha256', $v2),
            ],
            'first_difference_offset' => $firstDifference,
            'common_suffix_length' => $commonSuffixLength,
            'v1_changed_length' => $v1ChangedLength,
            'v2_changed_length' => $v2ChangedLength,
            'v1_context' => $this->context($v1, $contextStart, $contextLength),
            'v2_context' => $this->context($v2, $contextStart, $contextLength),
        ];
    }

    private function context(string $value, int $start, int $length): string
    {
        $context = substr($value, $start, $length);

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $context) ?? $context;
    }

    private function regionHtml(PublicContentDocument $document, string $name): string
    {
        $region = $document->regions[$name] ?? null;

        return $region instanceof ContentRegion
            ? $this->normaliseHtml($region->renderedHtml)
            : '';
    }

    private function normaliseHtml(string $html): string
    {
        $html = preg_replace(
            '/\bproduct-[a-f0-9]{13}\b/i',
            'product-[generated-id]',
            $html,
        ) ?? $html;

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
