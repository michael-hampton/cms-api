<?php

namespace App\Parsers\Renderers;

use App\Framework\Support\HtmlPurifier;
use App\Parsers\Dtos\BlockDtoInterface;

abstract class BaseBlockRenderer implements BlockRendererInterface
{
    /**
     * Get supported block type
     */
    abstract protected function getSupportedType(): string;

    public function supports(BlockDtoInterface $dto): bool
    {
        return $dto->getType() === $this->getSupportedType();
    }

    /**
     * Escape HTML
     */
    protected function escape(string $value): string
    {
        $purifier = new HtmlPurifier();

        return $purifier->purify($value);
    }

    /**
     * Escape and preserve line breaks
     */
    protected function escapeWithBreaks(string $value): string
    {
        return nl2br($this->escape($value));
    }

    protected function sanitizeBasicHtml(string $html): string
    {
        // Remove script and style blocks
        $html = preg_replace('#<(script|style).*?>.*?</\1>#is', '', $html);

        // Allow only basic tags
        $allowedTags = '<strong><em><i><u><p><br>';
        $html = strip_tags($html, $allowedTags);

        // Remove all attributes from allowed tags
        $html = preg_replace('/<(strong|em|i|u|p|br)(\s+[^>]*)?>/i', '<$1>', $html);

        return $html;
    }

}