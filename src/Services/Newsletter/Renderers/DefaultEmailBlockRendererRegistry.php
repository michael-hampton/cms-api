<?php

namespace App\Services\Newsletter\Renderers;

use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;

final class DefaultEmailBlockRendererRegistry implements EmailBlockRendererRegistry
{
    /**
     * @var EmailBlockRenderer[]
     */
    private array $renderers = [];

    /**
     * @param EmailBlockRenderer[] $renderers
     */
    public function __construct(array $renderers)
    {
        foreach ($renderers as $renderer) {
            $this->renderers[$renderer->type] = $renderer;
        }
    }

    public function all(): array
    {
        return array_values($this->renderers);
    }

    public function getFor(string $blockType): ?EmailBlockRenderer
    {
        return $this->renderers[$blockType] ?? null;
    }

    public function has(string $type): bool
    {
        return !empty($this->renderers[$type]);
    }

    public function render(string $type, BaseBlockData $data, ?NewsletterRenderContext $context)
    {
        if (!$this->has($type)) {
            throw new \InvalidArgumentException("Renderer for type '$type' not found");
        }

        $renderer = $this->getFor($type);
        return $renderer->render($data, $context);
    }
}
