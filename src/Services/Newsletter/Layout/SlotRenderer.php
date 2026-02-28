<?php

namespace App\Services\Newsletter\Layout;

use App\DTO\Newsletters\Layout\SlotDTO;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\Renderers\EmailBlockRendererRegistry;
use App\Services\Newsletter\Services\BlockDataFactory;

/**
 * Renders a single slot's blocks to HTML.
 * Delegates each block to EmailBlockRendererRegistry.
 * No newsletter-specific logic.
 */
class SlotRenderer
{
    public function __construct(
        private readonly EmailBlockRendererRegistry $blockRegistry,
        private readonly BlockDataFactory $blockDataFactory
    )
    {
    }

    public function render(SlotDTO $slot, ?NewsletterRenderContext $context = null): string
    {
        if ($slot->isEmpty()) {
            return '';
        }

        $htmlBlocks = [];

        foreach ($slot->blocks as $block) {
            $result = $this->renderBlock($block, $context);

            if (!empty($result['success']) && !empty($result['html'])) {
                $htmlBlocks[] = $result['html'];
            }
        }

        $htmlBlocks = array_filter(
            $htmlBlocks,
            fn($html) => trim($html) !== ''
        );

        if (empty($htmlBlocks)) {
            return '';
        }

        return sprintf(
            '<div class="layout-slot" data-slot="%s">%s</div>',
            htmlspecialchars($slot->name, ENT_QUOTES),
            implode("\n", $htmlBlocks)
        );
    }

    public function renderBlock(array $block, ?NewsletterRenderContext $context): array
    {
        try {
            $type = $block['type'] ?? null;

            if ($type === null) {
                throw new \Exception('Missing block type');
            }

            $blockData = $this->blockDataFactory->create(
                $type,
                $block['data'] ?? []
            );

            $renderedBlock = $this->blockRegistry->render(
                $type,
                $blockData,
                $context
            );

            return [
                'success' => true,
                'html' => $renderedBlock->html,
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'html' => '',
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ];
        }
    }
}