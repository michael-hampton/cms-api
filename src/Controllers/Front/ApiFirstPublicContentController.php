<?php

namespace App\Controllers\Front;

use App\Actions\PublicContent\RenderPublicContentPageAction;
use App\Controllers\Controller;
use App\Framework\Http\Response;
use App\Models\Page;

final class ApiFirstPublicContentController extends Controller
{
    public function __construct(private readonly RenderPublicContentPageAction $render)
    {
        parent::__construct();
    }

    public function show(Page $page): Response
    {
        return $this->render->execute($page);
    }
}
