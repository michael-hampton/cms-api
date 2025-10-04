<?php

namespace App\Renderers;

use App\Services\Url\UrlResolutionResult;

interface PageRendererInterface
{
    public function render(UrlResolutionResult $result): mixed;

}