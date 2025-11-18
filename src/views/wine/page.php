@include('header', ['menu' => $menu])
@css('base-blocks.css')


<?php
// views/estate/page.php (enhanced page template)
$title = $page->title ?? 'Premier Properties';
$description = $page->meta_description ?? 'Premier Properties - Luxury Real Estate in London';
?>

@include('main-content', ['page' => $page, 'blockParserService' => $blockParserService, 'todaysDeals' => $todaysDeals, 'footerMenu' => $footerMenu, 'pageGridHtml' => $pageGridHtml])')
