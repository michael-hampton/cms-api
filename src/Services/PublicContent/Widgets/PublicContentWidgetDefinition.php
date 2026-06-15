<?php

namespace App\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;

interface PublicContentWidgetDefinition
{
    public function key(): string;

    public function defaultPlacement(): WidgetPlacement;

    public function supports(PublicContentContext $context): bool;

    public function build(
        PublicContentContext $context,
        WidgetPlacement $placement,
    ): PublicContentComponent;
}
