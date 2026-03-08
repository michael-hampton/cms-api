<?php

namespace App\Services\Newsletter\Layout;

use App\DTO\Newsletters\Layout\SlotDTO;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\Renderers\EmailBlockRendererRegistry;
use App\Services\Newsletter\Services\BlockDataFactory;

/**
 * Renders a single slot's blocks to HTML.
 * Delegates each block to EmailBlockRendererRegistry.
 *
 * Variable resolution:
 *   Before a block's data reaches BlockDataFactory, LayoutBlockVariableResolver
 *   replaces any {{variable}} placeholders using values derived from the
 *   NewsletterRenderContext.  This keeps the renderers and DTOs clean — they
 *   always receive concrete values, never template syntax.
 */
class SlotRenderer
{
    public function __construct(
        private readonly EmailBlockRendererRegistry  $blockRegistry,
        private readonly BlockDataFactory            $blockDataFactory,
        private readonly LayoutBlockVariableResolver $variableResolver,
    )
    {
    }

    public function render(SlotDTO $slot, ?NewsletterRenderContext $context = null): string
    {
        if ($slot->isEmpty()) {
            return '';
        }

        // Build variable map once per slot render so we don't re-derive it
        // for every block. Map is empty when there is no context.
        $variableMap = $context !== null
            ? $this->variableResolver->buildVariableMap($context)
            : [];

        $htmlBlocks = [];

        foreach ($slot->blocks as $block) {
            $result = $this->renderBlock($block, $context, $variableMap);

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

    public function renderBlock(array $block, ?NewsletterRenderContext $context, array $variableMap = []): array
    {
        try {
            $type = $block['type'] ?? null;

            if ($type === null) {
                throw new \Exception('Missing block type');
            }

            // Resolve variables in the raw data array before hydration
            $rawData = $block['data'] ?? [];
            if (!empty($variableMap) && is_array($rawData)) {
                $rawData = $this->variableResolver->resolveBlock($rawData, $variableMap);
            }

            $blockData = $this->blockDataFactory->create(
                $type,
                $rawData
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