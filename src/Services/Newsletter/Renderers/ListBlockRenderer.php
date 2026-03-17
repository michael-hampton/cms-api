<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\ListBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class ListBlockRenderer implements EmailBlockRenderer
{
    public $type = 'list';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $context): RenderedBlock
    {
        if (!$blockData instanceof ListBlockData) {
            return RenderedBlock::skipped();
        }

        $tag = $blockData->listType;
        $baseListStyle = 'color: #333; font-size: 16px; line-height: 1.6; margin: 15px 0; padding-left: 30px;';
        $listStyle = $blockData->style->mergeIntoCss($baseListStyle);

        $html = [];
        $html[] = "<{$tag} style=\"{$listStyle}\">";

        foreach ($blockData->items as $item) {
            $html[] = '<li style="margin-bottom: 8px;">' . Str::sanitize($item) . '</li>';
        }

        $html[] = "</{$tag}>";

        return RenderedBlock::rendered(implode("\n", $html));
    }
}