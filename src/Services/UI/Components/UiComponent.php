<?php

namespace App\Services\UI\Components;

interface UiComponent
{
    public function key(): string;

    public function render(array $context = []): string;
}
