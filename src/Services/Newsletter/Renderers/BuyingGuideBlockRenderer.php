<?php declare(strict_types=1);

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\BuyingGuideBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class BuyingGuideBlockRenderer implements EmailBlockRenderer
{
    public $type = 'buying-guide';

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $context): RenderedBlock
    {
        if (!$blockData instanceof BuyingGuideBlockData) return RenderedBlock::skipped();
        $baseStyle = 'border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0;';
        $wrapperStyle = $blockData->style->mergeIntoCss($baseStyle);
        $baseTitleStyle = 'color: #333; margin: 0 0 10px 0; font-size: 22px;';
        $titleStyle = $blockData->style->mergeIntoCss($baseTitleStyle);
        $html = [];
        $html[] = "<div style=\"{$wrapperStyle}\">";
        if ($blockData->sponsored) $html[] = '<span style="background-color:#ffc107;color:#333;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:bold;display:inline-block;margin-bottom:15px;">Sponsored</span>';
        if ($blockData->image && isset($blockData->image['src'])) $html[] = '<img src="' . Str::sanitize($blockData->image['src']) . '" alt="' . Str::sanitize($blockData->title) . '" style="max-width:100%;height:auto;border-radius:4px;margin-bottom:15px;">';
        $html[] = "<h3 style=\"{$titleStyle}\">" . Str::sanitize($blockData->title) . '</h3>';
        if ($blockData->subtitle) $html[] = '<p style="color:#666;margin:0 0 20px 0;font-size:16px;">' . Str::sanitize($blockData->subtitle) . '</p>';
        if (!empty($blockData->specs)) {
            $html[] = '<h4 style="color:#333;margin:20px 0 10px 0;font-size:18px;">Specifications</h4><table style="width:100%;border-collapse:collapse;">';
            foreach ($blockData->specs as $spec) $html[] = '<tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;color:#333;">' . Str::sanitize($spec['text'] ?? '') . '</td><td style="padding:8px;border-bottom:1px solid #eee;color:#666;">' . Str::sanitize($spec['value'] ?? '') . '</td></tr>';
            $html[] = '</table>';
        }
        if ($blockData->showReviewPanel && (!empty($blockData->pros) || !empty($blockData->cons))) {
            $html[] = '<div style="margin-top:20px;"><table style="width:100%;"><tr>';
            if (!empty($blockData->pros)) {
                $html[] = '<td style="width:50%;padding-right:10px;vertical-align:top;"><h5 style="color:#28a745;margin:0 0 10px 0;">✓ Advantages</h5><ul style="margin:0;padding-left:20px;color:#333;">';
                foreach ($blockData->pros as $pro) $html[] = '<li style="margin-bottom:5px;">' . Str::sanitize($pro) . '</li>';
                $html[] = '</ul></td>';
            }
            if (!empty($blockData->cons)) {
                $html[] = '<td style="width:50%;padding-left:10px;vertical-align:top;"><h5 style="color:#dc3545;margin:0 0 10px 0;">✗ Considerations</h5><ul style="margin:0;padding-left:20px;color:#333;">';
                foreach ($blockData->cons as $con) $html[] = '<li style="margin-bottom:5px;">' . Str::sanitize($con) . '</li>';
                $html[] = '</ul></td>';
            }
            $html[] = '</tr></table></div>';
        }
        if ($blockData->url) $html[] = '<div style="margin-top:20px;"><a href="' . Str::sanitize($blockData->url) . '" style="display:inline-block;padding:12px 30px;background-color:#007bff;color:white;text-decoration:none;border-radius:4px;font-weight:bold;">' . Str::sanitize($blockData->linkText) . '</a></div>';
        $html[] = '</div>';
        return RenderedBlock::rendered(implode("\n", $html));
    }
}