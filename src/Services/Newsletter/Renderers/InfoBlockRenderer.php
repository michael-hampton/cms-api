<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\InfoBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class InfoBlockRenderer implements EmailBlockRenderer
{
    public $type = 'info';
    private const COLORS = [
        'info' => ['bg' => '#e7f3ff', 'border' => '#007bff'],
        'warning' => ['bg' => '#fff3cd', 'border' => '#ffc107'],
        'tip' => ['bg' => '#d4edda', 'border' => '#28a745'],
        'note' => ['bg' => '#f8f9fa', 'border' => '#6c757d']
    ];

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof InfoBlockData) {
            return RenderedBlock::skipped();
        }

        $color = self::COLORS[$blockData->infoType] ?? self::COLORS['info'];

        $html = [];
        $html[] = "<div style=\"background-color: {$color['bg']}; border-left: 4px solid {$color['border']}; padding: 15px; margin: 20px 0; border-radius: 4px;\">";
        $html[] = '<p style="margin: 0; color: #333; font-size: 16px; line-height: 1.6;">';
        $html[] = '<strong>' . ucfirst($blockData->infoType) . ':</strong> ' . Str::sanitize($blockData->description);
        $html[] = '</p>';
        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}