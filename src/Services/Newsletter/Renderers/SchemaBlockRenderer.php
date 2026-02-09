<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\SchemaBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class SchemaBlockRenderer implements EmailBlockRenderer
{
    public function supports(string $type): bool
    {
        return $type === 'schema';
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof SchemaBlockData) {
            return RenderedBlock::skipped();
        }

        if ($blockData->schemaType === 'question') {
            return $this->renderQuestion($blockData);
        }

        return $this->renderHowTo($blockData);
    }

    private function renderQuestion(SchemaBlockData $blockData): RenderedBlock
    {
        $html = [];
        $html[] = '<div style="background-color: #f8f9fa; border-left: 4px solid #007bff; padding: 20px; margin: 20px 0; border-radius: 4px;">';
        $html[] = '<h3 style="color: #333; margin: 0 0 10px 0; font-size: 18px;">' . Str::sanitize($blockData->question ?? '') . '</h3>';
        $html[] = '<p style="color: #333; margin: 0 0 10px 0; font-size: 16px; line-height: 1.6;">' . Str::sanitize($blockData->answer ?? '') . '</p>';

        if ($blockData->expansion) {
            $html[] = '<p style="color: #666; margin: 0; font-size: 14px; line-height: 1.6;">' . Str::sanitize($blockData->expansion) . '</p>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }

    private function renderHowTo(SchemaBlockData $blockData): RenderedBlock
    {
        $html = [];
        $html[] = '<div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0;">';

        if ($blockData->image && isset($blockData->image['src'])) {
            $html[] = '<img src="' . Str::sanitize($blockData->image['src']) . '" alt="' . Str::sanitize($blockData->title ?? '') . '" style="max-width: 100%; height: auto; border-radius: 4px; margin-bottom: 15px;">';
        }

        $html[] = '<h3 style="color: #333; margin: 0 0 10px 0; font-size: 20px;">' . Str::sanitize($blockData->title ?? '') . '</h3>';

        if ($blockData->description) {
            $html[] = '<p style="color: #666; margin: 0; font-size: 16px; line-height: 1.6;">' . Str::sanitize($blockData->description) . '</p>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}