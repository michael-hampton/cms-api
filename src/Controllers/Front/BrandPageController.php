<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Tag;
use App\Services\Cms\ArticleAccessService;
use App\Services\Cms\MenuRenderer;
use App\Services\Subscriptions\SubscriptionModalService;

class BrandPageController extends Controller
{
    public function __construct(
        private readonly ArticleAccessService     $articleAccessService,
        private readonly SubscriptionModalService $subscriptionModalService,
    )
    {
        parent::__construct();
    }

    public function show(string $slug)
    {
        // check if has corresponding page tag
        $tag = Tag::with(['categories'])->where('slug', $slug)->first();

        if (!$tag) {
            return $this->notFound();
        }

        // Get current page from query string
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 12;

        // Get paginated pages for this tag
        $paginationData = Page::whereHas('tags', function ($query) use ($tag) {
            $query->where('tags.id', $tag->id);
        })
            ->where('status', 'Published')
            ->with(['tags', 'authors', 'categories'])
            ->orderBy('published_at', 'desc')
            ->paginate($perPage, $currentPage);

        $pages = $paginationData['data'];
        $member = MemberAuth::getMember();

        $pages->map(function ($page) use ($member) {
            $accessInfo = $this->articleAccessService->enrichPageWithAccessInfo($page, $member);
            $page->access = $accessInfo;
        });

        $pagination = [
            'current_page' => $paginationData['pagination']['current_page'],
            'per_page' => $paginationData['pagination']['per_page'],
            'total' => $paginationData['pagination']['total'],
            'last_page' => $paginationData['pagination']['last_page'],
            'from' => $paginationData['pagination']['from'],
            'to' => $paginationData['pagination']['to']
        ];

        $menu = Menu::where('is_active', true)
            ->where('site_id', SiteContext::getId())
            ->where('menu_type', 'header')
            ->with(['items'])
            ->first();

        $siteId = SiteContext::getId();
        $modalData = $this->subscriptionModalService->getModalData($member, $siteId);

        return $this->view('brand.show', [
            'pages' => $pages,
            'tag' => $tag,
            'pagination' => $pagination,
            'menu' => $menu,
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