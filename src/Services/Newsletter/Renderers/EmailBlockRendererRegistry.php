<?php

namespace App\Services\Newsletter\Renderers;

use App\Services\Newsletter\Contracts\EmailBlockRenderer;

interface EmailBlockRendererRegistry
{
    /**
     * @return EmailBlockRenderer[]
     */
    public function all(): array;

    public function getFor(string $blockType): ?EmailBlockRenderer;
}