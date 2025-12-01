@include('header', ['menu' => $menu])

@include('main-content', ['page' => $page, 'blockParserService' => $blockParserService, 'todaysDeals' => $todaysDeals, 'footerMenu' => $footerMenu, 'pageGrid' => $pageGrid, 'categories' => $allCategories, 'subscriptionModalData' => $subscriptionModalData])')
