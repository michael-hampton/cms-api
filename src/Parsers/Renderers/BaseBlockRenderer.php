<?php

namespace App\Parsers\Renderers;

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
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Escape and preserve line breaks
     */
    protected function escapeWithBreaks(string $value): string
    {
        return nl2br($this->escape($value));
    }
}