<?php

namespace App\Services\Newsletter\Renderers;

use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;

interface EmailBlockRendererRegistry
{
    /**
     * @return EmailBlockRenderer[]
     */
    public function all(): array;

    public function getFor(string $blockType): ?EmailBlockRenderer;

    public function has(string $type);

    public function render(string $type, BaseBlockData $data, ?NewsletterRenderContext $context);
}