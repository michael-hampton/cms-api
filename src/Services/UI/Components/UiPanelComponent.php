<?php

namespace App\Services\UI\Components;

interface UiPanelComponent
{
    public function key(): string;

    public function mode(): string;

    /**
     * @return array<string, mixed>
     */
    public function descriptor(array $context = []): array;

    public function render(array $context = []): ?string;
}
