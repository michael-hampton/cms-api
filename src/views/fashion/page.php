@include('header', ['menu' => $menu])

@css('base-blocks.css')

@include('main-content', ['page' => $page, 'blockParserService' => $blockParserService, 'todaysDeals' => $todaysDeals, 'footerMenu' => $footerMenu, 'pageGridHtml' => $pageGridHtml])')
