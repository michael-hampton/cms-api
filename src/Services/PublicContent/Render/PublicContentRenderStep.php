<?php

namespace App\Services\PublicContent\Render;

interface PublicContentRenderStep
{
    public function name(): string;

    public function handle(PublicContentRenderContext $context): PublicContentRenderContext;
}
