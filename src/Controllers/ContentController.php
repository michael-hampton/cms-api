<?php

namespace App\Controllers;

use App\Models\Menu;
use App\Models\Page;
use App\Repositories\CommentRepository;
use App\Services\BlockParserService;
use App\Services\EstateWebsiteService;
use App\Services\MenuRenderer;
use App\Services\Url\UrlResolutionResult;

class ContentController extends Controller
{
    public function __construct(
        private BlockParserService $blockParserService,
        private CommentRepository $commentRepository
    ) {
        parent::__construct();
    }

    public function show(Page $page, UrlResolutionResult $urlResolutionResult)
    {
        $menu = Menu::where('is_active', true)->with(['items'])->first();

        // Load page relationships
        $page->load([
            'blocks', 'categories', 'tags', 'metadata',
            'seo', 'settings', 'social', 'customFields', 'author'
        ]);

        $data = [
            'menu' => $menu,
            'page' => $page,
            'blockParserService' => $this->blockParserService
        ];

        // Load comments for blog pages
        if ($page->page_type === 'blog') {
            $data['comments'] = $this->commentRepository->getPageComments($page->id, 'approved');
        }

        return $this->view('estate/page', $data);
    }
}