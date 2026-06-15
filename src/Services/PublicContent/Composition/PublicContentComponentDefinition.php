<?php

namespace App\Services\PublicContent\Composition;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\Framework\View\ViewRenderer;

final readonly class PublicContentComponentDefinition
{
    public function __construct(
        private ViewRenderer $views,
        private string $id,
        private string $type,
        private string $template,
        private string $region,
        private int $priority,
        private array $styles = [],
        private array $scripts = [],
        private mixed $endpoints = null,
        private bool $stateful = false,
        private mixed $supports = null,
        private mixed $data = null,
    ) {
    }

    public function supports(PublicContentContext $context): bool
    {
        return $this->supports === null || (bool)($this->supports)($context);
    }

    public function build(PublicContentContext $context): PublicContentComponent
    {
        $extra = $this->data === null ? [] : (array)($this->data)($context);
        $endpoints = $this->endpoints === null
            ? []
            : (is_callable($this->endpoints) ? (array)($this->endpoints)($context) : (array)$this->endpoints);

        $styles = $this->type === 'deals-carousel' ? [] : $this->styles;
        $scripts = $this->type === 'deals-carousel' ? [] : $this->scripts;

        return new PublicContentComponent(
            id: $this->id,
            type: $this->type,
            region: $this->region,
            priority: $this->priority,
            html: $this->views->partial($this->template, $context->with($extra)),
            styles: array_map(
                static fn(string $file): string => asset($file, 'css'),
                $styles,
            ),
            scripts: array_map(
                static fn(string $file): string => asset($file, 'js'),
                $scripts,
            ),
            endpoints: $endpoints,
            stateful: $this->stateful,
        );
    }
}
