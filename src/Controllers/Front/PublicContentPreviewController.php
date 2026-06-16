<?php

namespace App\Controllers\Front;

use App\Actions\PublicContent\RenderPublicContentPageAction;
use App\Controllers\Controller;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\PublicContent\PublicContentRollout;

final class PublicContentPreviewController extends Controller
{
    public function __construct(
        private readonly PublicContentRollout $rollout,
        private readonly PublicContentPageRepository $pages,
        private readonly RenderPublicContentPageAction $render,
    ) {
        parent::__construct();
    }

    public function show(string $slug): Response
    {
        if (!$this->rollout->previewEnabled()) {
            return $this->notFound('Public content preview is disabled.');
        }

        $page = $this->pages->findPublishedBySlug(
            SiteContext::getId(),
            $slug,
        );

        if (!$page) {
            return $this->notFound('Content not found.');
        }

        return $this->render->execute($page, true);
    }
}
