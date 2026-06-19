<?php

namespace App\Services\PublicContent\Composition;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\Framework\View\ViewRenderer;
use App\Services\PublicContent\Widgets\PublicContentWidgetDefinition;
use App\Services\PublicContent\Widgets\WidgetPlacement;

final readonly class PublicContentComponentDefinition implements PublicContentWidgetDefinition
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

    public function key(): string
    {
        return $this->id;
    }

    public function defaultPlacement(): WidgetPlacement
    {
        return new WidgetPlacement(
            widgetKey: $this->id,
            region: $this->region,
            priority: $this->priority,
        );
    }

    public function supports(PublicContentContext $context): bool
    {
        $pageTypes = config("public_content.widgets.{$this->id}.page_types", ['*']);

        if (is_array($pageTypes)
            && !in_array('*', $pageTypes, true)
            && !in_array((string) $context->page->page_type, $pageTypes, true)
        ) {
            return false;
        }

        return $this->supports === null || (bool) ($this->supports)($context);
    }

    public function build(
        PublicContentContext $context,
        ?WidgetPlacement $placement = null,
    ): PublicContentComponent {
        $placement ??= $this->defaultPlacement();
        $extra = $this->data === null ? [] : (array) ($this->data)($context);
        $extra['widgetConfiguration'] = $placement->configuration;

        $endpoints = $this->endpoints === null
            ? []
            : (is_callable($this->endpoints) ? (array) ($this->endpoints)($context) : (array) $this->endpoints);

        $styles = $this->type === 'deals-carousel' ? [] : $this->styles;
        $scripts = $this->type === 'deals-carousel' ? [] : $this->scripts;

        if ($this->type === 'comments') {
            $scripts[] = 'public-comments.js';
            $endpoints['csrf_token'] = csrf_token();
        }

        return new PublicContentComponent(
            id: $this->id,
            type: $this->type,
            region: $placement->region,
            priority: $placement->priority,
            html: $this->views->partial($this->template, $context->with($extra)),
            styles: array_map(
                static fn(string $file): string => asset($file, 'css'),
                $styles,
            ),
            scripts: array_map(
                static fn(string $file): string => asset($file, 'js'),
                array_values(array_unique($scripts)),
            ),
            endpoints: $endpoints,
            stateful: $this->stateful,
        );
    }
}
