<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\TextBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class TextBlockRenderer implements EmailBlockRenderer
{
    public $type = 'text';
    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof TextBlockData) {
            return RenderedBlock::skipped();
        }

        $html = [];
        foreach ($blockData->paragraphs as $paragraph) {
            $html[] = '<p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;">';
            $html[] = Str::sanitize($paragraph);
            $html[] = '</p>';
        }

        return RenderedBlock::rendered(implode("\n", $html));
    }
}