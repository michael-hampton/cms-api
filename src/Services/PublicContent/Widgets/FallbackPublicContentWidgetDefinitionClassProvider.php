<?php
namespace App\Services\PublicContent\Widgets;

final class FallbackPublicContentWidgetDefinitionClassProvider implements PublicContentWidgetDefinitionClassProvider
{
    public function __construct(
        private readonly PublicContentWidgetDefinitionClassProvider $database,
        private readonly PublicContentWidgetDefinitionClassProvider $file,
    ) {
    }

    public function has(): bool
    {
        return true;
    }

    /** @return list<class-string> */
    public function all(): array
    {
        return $this->database->has() ? $this->database->all() : $this->file->all();
    }
}