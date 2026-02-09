<?php

namespace App\Services\Newsletter\Contracts;

use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

interface EmailBlockRenderer
{
    /**
     * Check if this renderer supports the given block type
     */
    public function supports(string $type): bool;

    /**
     * Render a block to HTML
     *
     * @param BaseBlockData $blockData
     * @param NewsletterRenderContext $newsletterRenderContext
     * @return RenderedBlock
     */
    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock;
}