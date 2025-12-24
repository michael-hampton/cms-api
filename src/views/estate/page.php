@include('header', ['menu' => $menu])


@include('main-content', ['page' => $page, 'blockParserService' => $blockParserService, 'todaysDeals' => $todaysDeals, 'footerMenu' => $footerMenu, 'categories' => $allCategories, 'subscriptionModalData' => $subscriptionModalData, 'html' => $html])
