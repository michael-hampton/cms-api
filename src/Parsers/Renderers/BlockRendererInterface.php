<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;

interface BlockRendererInterface
{
    /**
     * Render block DTO to HTML
     */
    public function render(BlockDtoInterface $dto): string;

    /**
     * Check if renderer supports this DTO type
     */
    public function supports(BlockDtoInterface $dto): bool;
}