<?php

namespace App\Controllers\Front;

use App\Actions\PublicContent\RenderPublicContentPageAction;
use App\Framework\Http\Response;
use App\Models\Page;
use App\Services\Url\UrlResolutionResult;

final class PublicContentPageController
{
    public function __construct(
        private readonly RenderPublicContentPageAction $render,
    ) {
    }

    public function show(Page $page, UrlResolutionResult $result): Response
    {
        return $this->render->execute($page);
    }
}
