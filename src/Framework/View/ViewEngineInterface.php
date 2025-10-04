<?php

namespace App\Framework\View;

interface ViewEngineInterface
{
    public function render(string $template, array $data = []): string;
    public function exists(string $template): bool;
}