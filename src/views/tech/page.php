@include('header', ['menu' => $menu, 'menuRenderer' => $menuRenderer])

@include('main-content', ['page' => $page, 'blockParserService' => $blockParserService, 'todaysDeals' => $todaysDeals, 'footerMenu' => $footerMenu, 'pageGridHtml' => $pageGridHtml, 'categories' => $allCategories, 'subscriptionModalData' => $subscriptionModalData])')
