<?php

namespace App\Services\UI\Components;

use App\Framework\View\ViewRenderer;

abstract class ViewComponent implements UiPanelComponent
{
    public function __construct(
        protected readonly ViewRenderer $viewRenderer,
    ) {
    }

    protected function renderView(string $template, array $context = []): string
    {
        return $this->viewRenderer->render($template, $context);
    }

    public function mode(): string
    {
        return 'server';
    }

    public function descriptor(array $context = []): array
    {
        return [
            'key' => $this->key(),
            'mode' => $this->mode(),
            'component' => static::class,
        ];
    }

    public function render(array $context = []): ?string
    {
        return null;
    }
}
