<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\TableBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class TableBlockRenderer implements EmailBlockRenderer
{
    public $type = 'table';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $context): RenderedBlock
    {
        if (!$blockData instanceof TableBlockData) {
            return RenderedBlock::skipped();
        }

        if (empty($blockData->rows)) {
            return RenderedBlock::skipped();
        }

        $baseTableStyle = 'width: 100%; border-collapse: collapse; margin: 20px 0;';
        $tableStyle = $blockData->style->mergeIntoCss($baseTableStyle);

        $html = [];
        $html[] = "<table style=\"{$tableStyle}\">";

        foreach ($blockData->rows as $index => $row) {
            $isHeader = $blockData->hasHeader && $index === 0;
            $html[] = '<tr>';

            foreach ($row as $cell) {
                $content = Str::sanitize($cell);
                if ($isHeader) {
                    $html[] = '<th style="background-color: #f8f9fa; color: #333; padding: 12px; text-align: left; border: 1px solid #ddd; font-weight: bold;">' . $content . '</th>';
                } else {
                    $html[] = '<td style="padding: 12px; border: 1px solid #ddd; color: #333;">' . $content . '</td>';
                }
            }

            $html[] = '</tr>';
        }

        $html[] = '</table>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}