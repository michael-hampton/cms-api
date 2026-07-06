<?php
namespace App\Services\PublicContent\Widgets;

final class FilePublicContentWidgetDefinitionClassProvider implements PublicContentWidgetDefinitionClassProvider
{
    public function has(): bool
    {
        return true;
    }

    /** @return list<class-string> */
    public function all(): array
    {
        return (array) config('public_content.widget_definitions', []);
    }
}