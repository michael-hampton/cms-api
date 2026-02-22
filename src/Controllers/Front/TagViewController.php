<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\DTO\Pages\PageFilterDto;
use App\Enums\Pages\PageFilterType;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Repositories\Cms\MenuRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\MenuRenderer;
use App\Services\Cms\Pages\ArticleAccessService;
use App\Services\Subscriptions\SubscriptionModalService;
use Exception;

class TagViewController extends Controller
{
    public function __construct(
        private readonly TagRepository            $tagRepository,
        private readonly PageRepository $pageRepository,
        private readonly MenuRepository $menuRepository,
        private readonly ArticleAccessService     $articleAccessService,
        private readonly SubscriptionModalService $subscriptionModalService,
    )
    {
        parent::__construct();
    }

    public function show(string $slug)
    {
        try {
            $tag = $this->tagRepository->findBySlug($slug);

            if (!$tag) {
                return $this->notFound();
            }

            $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = 12;
            $sort = $_GET['sort'] ?? 'latest';
            $categoryFilter = $_GET['category'] ?? '';
            $authorFilter = $_GET['author'] ?? '';

            $secondary = [];
            if ($categoryFilter) {
                $secondary['category'] = (int)$categoryFilter;
            }
            if ($authorFilter) {
                $secondary['author'] = (int)$authorFilter;
            }

            $filter = PageFilterDto::make(
                filterType: PageFilterType::Tag,
                filterId: $tag->id,
                sort: $sort,
                status: 'published',
                currentPage: PageFilterDto::sanitisePage($_GET['page'] ?? 1),
                secondary: $secondary,
            );

            $paginationData = $this->pageRepository->getPaginatedPages($filter);

            $pages = $paginationData['data'];
            $member = MemberAuth::getMember();

            $pages->map(function ($page) use ($member) {
                $page->access = $this->articleAccessService->enrichPageWithAccessInfo($page, $member);
            });

            $siteId = SiteContext::getId();
            $modalData = $this->subscriptionModalService->getModalData($member, $siteId);

            return $this->view('estate/tag', [
                'pages' => $pages,
                'menu' => $this->menuRepository->findActiveHeaderMenu($siteId),
                'tag' => $tag,
                'pagination' => $paginationData['pagination'],
                'currentSort' => $sort,
                'menuRenderer' => new MenuRenderer(),
                'subscriptionModalData' => $modalData,
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo 'An error occurred: ' . htmlspecialchars($e->getMessage());
        }
    }

//    protected function notFound()
//    {
//        http_response_code(404);
//        return $this->view('estate/404', [
//            'menu' => Menu::where('is_active', true)->with(['items'])->first()
//        ]);
//    }
}