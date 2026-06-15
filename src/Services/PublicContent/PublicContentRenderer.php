<?php

namespace App\Services\PublicContent;

use App\DTO\PublicContent\ContentRegion;
use App\Models\Page;
use App\Parsers\BlockFactory;
use App\Repositories\Cms\BlockRepository;
use App\Services\Cms\Pages\BlockParserService;
use Throwable;

final class PublicContentRenderer
{
    public function __construct(
        private readonly BlockRepository $blocks,
        private readonly BlockFactory $blockFactory,
        private readonly BlockParserService $blockParser,
    ) {
    }

    /**
     * Structured block data is canonical. rendered_html is a transitional fallback.
     *
     * @return array<string, ContentRegion>
     */
    public function render(Page $page, int $siteId): array
    {
        $regions = [
            'main' => ['blocks' => [], 'html' => ''],
            'sidebar' => ['blocks' => [], 'html' => ''],
        ];

        foreach ($this->blocks->getPageBlocks($page->id) as $block) {
            $raw = is_array($block->data)
                ? $block->data
                : (json_decode((string)$block->data, true) ?: []);

            $region = ($raw['context'] ?? 'default') === 'sidebar' ? 'sidebar' : 'main';
            $input = array_merge($raw, ['type' => $block->type]);

            try {
                $dto = $this->blockFactory->make($input);
                $structured = $dto->toArray();
            } catch (Throwable) {
                $structured = $raw;
            }

            $renderedHtml = '';
            try {
                $renderedHtml = $this->blockParser->buildBlock(
                    (int)$block->page_id,
                    $input,
                    (int)$block->order,
                    false,
                    $siteId,
                );
            } catch (Throwable $exception) {
                error_log("Failed to render public content block {$block->id}: {$exception->getMessage()}");
            }

            $regions[$region]['blocks'][] = [
                'id' => (int)$block->id,
                'type' => (string)$block->type,
                'order' => (int)$block->order,
                'data' => $structured,
                'rendered_html' => $renderedHtml,
            ];
            $regions[$region]['html'] .= $renderedHtml;
        }

        return [
            'main' => new ContentRegion('main', $regions['main']['blocks'], $regions['main']['html']),
            'sidebar' => new ContentRegion('sidebar', $regions['sidebar']['blocks'], $regions['sidebar']['html']),
        ];
    }
}
