<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\DTO\Pages\PageFilterDto;
use App\Enums\Pages\PageFilterType;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Cms\AuthorRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\MenuRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\MenuRenderer;
use App\Services\Cms\Pages\ArticleAccessService;
use App\Services\Subscriptions\SubscriptionModalService;
use Exception;

class AuthorViewController extends Controller
{
    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly PageRepository   $pageRepository,
        private readonly MenuRepository   $menuRepository,
        private readonly CategoryRepository       $categoryRepository,
        private readonly TagRepository            $tagRepository,
        private readonly ArticleAccessService     $articleAccessService,
        private readonly SubscriptionModalService $subscriptionModalService,
    )
    {
        parent::__construct();
    }

    public function show(string $slug, Request $request)
    {
        try {

            $author = $this->authorRepository->findBySlug($slug);

            if (!$author) {
                http_response_code(404);
                return;
            }

            $sort = $request->input('sort') ?? 'latest';
            $categoryFilter = $request->input('category') ?? '';

            $filter = PageFilterDto::make(
                filterType: PageFilterType::Author,
                filterId: $author->id,
                sort: $sort,
                status: 'published',
                currentPage: PageFilterDto::sanitisePage($_GET['page'] ?? 1),
                secondary: $categoryFilter ? ['category' => $categoryFilter] : [],
            );

            $paginationData = $this->pageRepository->getPaginatedPages($filter);

            $member = MemberAuth::getMember();
            $pages = $paginationData['data'];

            $pages->map(function ($page) use ($member) {
                $page->access = $this->articleAccessService->enrichPageWithAccessInfo($page, $member);
            });

            $siteId = SiteContext::getId();
            $modalData = $this->subscriptionModalService->getModalData($member, $siteId);

            return $this->view('estate/author', [
                'author' => $author,
                'menu' => $this->menuRepository->findActiveHeaderMenu($siteId),
                'pages' => $pages,
                'pagination' => $paginationData['pagination'],
                'currentSort' => $sort,
                'categories' => $this->categoryRepository->getActive(),
                'tags' => $this->tagRepository->all(),
                'menuRenderer' => new MenuRenderer(),
                'subscriptionModalData' => $modalData,
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo 'An error occurred: ' . htmlspecialchars($e->getMessage());
        }
    }
}