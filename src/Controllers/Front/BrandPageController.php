<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\DTO\Pages\PageFilterDto;
use App\Enums\Pages\PageFilterType;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Cms\MenuRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\MenuRenderer;
use App\Services\Cms\Pages\ArticleAccessService;
use App\Services\Subscriptions\SubscriptionModalService;

class BrandPageController extends Controller
{
    public function __construct(
        private readonly TagRepository  $tagRepository,
        private readonly PageRepository $pageRepository,
        private readonly MenuRepository $menuRepository,
        private readonly ArticleAccessService     $articleAccessService,
        private readonly SubscriptionModalService $subscriptionModalService,
    )
    {
        parent::__construct();
    }

    public function show(string $slug, Request $request)
    {
        $tag = $this->tagRepository->findBySlug($slug);

        if (!$tag) {
            return $this->notFound();
        }

        // Load categories relation needed by the view
        $tag->load(['categories']);

        $filter = PageFilterDto::make(
            filterType: PageFilterType::Brand,
            filterId: $tag->id,
            sort: 'latest',
            status: 'Published',
            currentPage: PageFilterDto::sanitisePage($request->input('page') ?? 1),
        );

        $paginationData = $this->pageRepository->getPaginatedPages($filter);

        $pages = $paginationData['data'];
        $member = MemberAuth::getMember();

        $pages->map(function ($page) use ($member) {
            $page->access = $this->articleAccessService->enrichPageWithAccessInfo($page, $member);
        });

        $siteId = SiteContext::getId();
        $modalData = $this->subscriptionModalService->getModalData($member, $siteId);

        return $this->view('brand.show', [
            'pages' => $pages,
            'tag' => $tag,
            'pagination' => $paginationData['pagination'],
            'menu' => $this->menuRepository->findActiveHeaderMenu($siteId),
            'menuRenderer' => new MenuRenderer(),
            'subscriptionModalData' => $modalData,
        ]);
    }

//    private function notFound()
//    {
//        http_response_code(404);
//        return $this->view('estate/404', [
//            'menu' => Menu::where('is_active', true)->with(['items'])->first()
//        ]);
//    }

}