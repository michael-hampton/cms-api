<?php
namespace App\Services\PublicContent\Widgets;

interface PublicContentWidgetDefinitionClassProvider
{
    public function has(): bool;

    /** @return list<class-string> */
    public function all(): array;
}