<?php
// API routes (return arrays -> converted to JSON)
use App\Controllers\AuthController;
use App\Controllers\AuthorController;
use App\Controllers\BlockController;
use App\Controllers\BrandController;
use App\Controllers\CartController;
use App\Controllers\CategoryController;
use App\Controllers\CommentController;
use App\Controllers\CustomFieldDefinitionController;
use App\Controllers\EstateWebsiteController;
use App\Controllers\ImageController;
use App\Controllers\MenuController;
use App\Controllers\MenuItemController;
use App\Controllers\NewsletterController;
use App\Controllers\OrderController;
use App\Controllers\PageController;
use App\Controllers\PageGridController;
use App\Controllers\PageHistoryController;
use App\Controllers\PreviewController;
use App\Controllers\ProductController;
use App\Controllers\ProductListController;
use App\Controllers\RegionSetController;
use App\Controllers\ReviewController;
use App\Controllers\SearchController;
use App\Controllers\SiteController;
use App\Controllers\TagController;
use App\Controllers\TerritoryController;
use App\Controllers\UserController;
use App\Controllers\VideoController;
use App\Controllers\VoucherController;
use App\Controllers\WishlistController;
use App\Framework\Authorization\AuthenticateWithToken;

$router->group(['prefix' => 'api', 'middleware' => AuthenticateWithToken::class], function ($router) {
    // Pages API
    $router->group(['prefix' => '{siteName}'], function ($router) {
        $router->get('/contact-info', SiteController::class, 'getContactInfo');

        $router->get('/pages', PageController::class, 'index');
        $router->post('/pages', PageController::class, 'store', [AuthenticateWithToken::class]);
        $router->post('/pages/bulk-update', PageController::class, 'bulkUpdate', [AuthenticateWithToken::class]);
        $router->get('/pages/block-types', PageController::class, 'getAvailableBlockTypes', [AuthenticateWithToken::class]);
        $router->get('/pages/{id}', PageController::class, 'show');
        $router->put('/pages/{id}', PageController::class, 'update');
        $router->delete('/pages/{id}', PageController::class, 'destroy');
        $router->get('/block-types', PageController::class, 'getAvailableBlockTypes');
        $router->post('/pages/{id}/duplicate', PageController::class, 'duplicate');
        $router->post('/pages/{id}/clone-to-site', PageController::class, 'cloneToSite');
        $router->get('/featured-pages', PageController::class, 'getFeaturedPages');

        $router->get('/pages/{pageId}/custom-fields/grouped', CustomFieldDefinitionController::class, 'getCustomFieldsGrouped');;

        // Categories API
        $router->get('/categories', CategoryController::class, 'index');
        $router->post('/categories', CategoryController::class, 'store');
        $router->get('/categories/{id}', CategoryController::class, 'show');
        $router->put('/categories/{id}', CategoryController::class, 'update');
        $router->delete('/categories/{id}', CategoryController::class, 'destroy');
        $router->get('/categories/{id}/check-delete', CategoryController::class, 'checkDelete');
        $router->post('/categories/{id}/duplicate', CategoryController::class, 'duplicate');

        // Brands
        $router->get('/brands', BrandController::class, 'index');
        $router->post('/brands', BrandController::class, 'store');
        $router->get('/brands/{id}', BrandController::class, 'show');
        $router->put('/brands/{id}', BrandController::class, 'update');
        $router->delete('/brands/{id}', BrandController::class, 'destroy');
        $router->get('/brands/{id}/check-delete', BrandController::class, 'checkDelete');
        $router->get('/brands/{id}/alternatives', BrandController::class, 'alternatives');
        $router->post('/brands/merge', BrandController::class, 'merge');
        $router->get('/brands/active', BrandController::class, 'active');
        $router->post('/brands/{id}/duplicate', BrandController::class, 'duplicate');

        //Orders
        $router->get('/orders', OrderController::class, 'index');
        $router->post('orders', OrderController::class, 'store');
        $router->get('/orders/by-status', OrderController::class, 'byStatus');
        $router->get('/orders/revenue', OrderController::class, 'revenue');
        $router->get('/orders/by-user/{userId}', OrderController::class, 'byUser');
        $router->get('/orders/{id}', OrderController::class, 'show');
        $router->put('/orders/{id}', OrderController::class, 'update');
        $router->put('/orders/{id}/items', OrderController::class, 'updateItems');
        $router->delete('/orders/{id}', OrderController::class, 'destroy');
        $router->post('/orders/{id}/cancel', OrderController::class, 'cancel');
        $router->post('/orders/{id}/complete', OrderController::class, 'complete');
        $router->post('/orders/{id}/refund', OrderController::class, 'refund');
        $router->post('/orders/{id}/duplicate', OrderController::class, 'duplicate');

        // Tags API
        $router->get('/tags', TagController::class, 'index');
        $router->get('/tags/cloud', TagController::class, 'cloud');
        $router->post('/tags', TagController::class, 'store');
        $router->get('/tags/{id}', TagController::class, 'show');
        $router->put('/tags/{id}', TagController::class, 'update');
        $router->delete('/tags/{id}', TagController::class, 'destroy');
        $router->post('/tags/cleanup', TagController::class, 'cleanup');
        $router->get('/featured-tags', TagController::class, 'featured');
        $router->get('/popular-tags', TagController::class, 'popular');
        $router->get('/tags/{id}/check-delete', TagController::class, 'checkDelete');
        $router->post('/tags/{id}/duplicate', TagController::class, 'duplicate');

        // Custom Fields API
        $router->get('/custom-fields', CustomFieldDefinitionController::class, 'index');
        $router->get('/custom-fields/grouped', CustomFieldDefinitionController::class, 'grouped');
        $router->get('/custom-fields/required', CustomFieldDefinitionController::class, 'required');
        $router->get('/custom-fields/searchable', CustomFieldDefinitionController::class, 'searchable');
        $router->post('/custom-fields', CustomFieldDefinitionController::class, 'store');
        $router->get('/custom-fields/{id}', CustomFieldDefinitionController::class, 'show');
        $router->put('/custom-fields/{id}', CustomFieldDefinitionController::class, 'update');
        $router->delete('/custom-fields/{id}', CustomFieldDefinitionController::class, 'destroy');

        // Menu
        $router->get('/menu', MenuController::class, 'index');
        $router->post('/menu', MenuController::class, 'store');
        $router->get('/menu/{id}', MenuController::class, 'show');
        $router->get('/menu/{id}/hierarchy', MenuController::class, 'hierarchy');
        $router->get('/menu/slug/{slug}', MenuController::class, 'getMenuBySlug');
        $router->put('/menu/{id}', MenuController::class, 'update');
        $router->delete('/menu/{id}', MenuController::class, 'destroy');

        // Images
        $router->get('/images', ImageController::class, 'index');
        $router->post('/images', ImageController::class, 'store');
        $router->get('/images/{id}', ImageController::class, 'show');
        $router->put('/images/{id}', ImageController::class, 'update');
        $router->delete('/images/{id}', ImageController::class, 'destroy');
        $router->post('/images/{id}/duplicate', ImageController::class, 'duplicate');

        // Bulk operations
        $router->delete('/images/bulk', ImageController::class, 'bulkDestroy');

// Utility routes
        $router->get('/image-recent', ImageController::class, 'recent');
        $router->get('/images/statistics', ImageController::class, 'statistics');
        $router->get('/images/unused', ImageController::class, 'unused');
        $router->post('/images/cleanup', ImageController::class, 'cleanup');

// Usage tracking
        $router->post('/image-track-usage', ImageController::class, 'trackUsage');
        $router->post('/image-remove-usage', ImageController::class, 'removeUsage');

// Category management
        $router->get('/image-categories', ImageController::class, 'categories');
        $router->post('/image-categories', ImageController::class, 'createCategory');

// Author API Routes
        $router->get('/authors', AuthorController::class, 'index');
        $router->get('/authors/active', AuthorController::class, 'getActive');
        $router->post('/authors', AuthorController::class, 'store');
        $router->post('/authors/merge', AuthorController::class, 'merge');
        $router->get('/authors/{id}', AuthorController::class, 'show');
        $router->put('/authors/{id}', AuthorController::class, 'update');
        $router->delete('/authors/{id}', AuthorController::class, 'destroy');
        $router->get('/authors/{id}/check-delete', AuthorController::class, 'checkDelete');
        $router->post('/authors/duplicate/{id}', AuthorController::class, 'duplicate');


//products
        $router->get('/products', ProductController::class, 'index');
        $router->post('/products', ProductController::class, 'store');
        $router->get('/products/{id}', ProductController::class, 'show');
        $router->put('/products/{id}', ProductController::class, 'update');
        $router->delete('/products/{id}', ProductController::class, 'destroy');
        $router->post('/products/{id}/duplicate', ProductController::class, 'duplicate');
        $router->get('/products/{id}/price-history', ProductController::class, 'priceHistory');


        $router->get('/users', UserController::class, 'index');
        $router->post('/users', UserController::class, 'store');
        $router->get('/users/{id}', UserController::class, 'show');
        $router->put('/users/{id}', UserController::class, 'update');
        $router->delete('/users/{id}', UserController::class, 'destroy');


        // Page History routes
        $router->get('/pages/{pageId}/history', PageHistoryController::class, 'index');
        $router->get('/history/{id}', PageHistoryController::class, 'show');
        $router->get('/history/recent', PageHistoryController::class, 'recent');
        $router->get('/users/{userId}/history', PageHistoryController::class, 'userHistory');
        $router->post('/history/{historyId}/restore', PageHistoryController::class, 'restore');


        $router->get('/page-grids', PageGridController::class, 'index');
        $router->post('/page-grids', PageGridController::class, 'store');
        $router->get('/page-grids/slug/{slug}', PageGridController::class, 'showBySlug');
        $router->get('/page-grids/{id}', PageGridController::class, 'show');
        $router->put('/page-grids/{id}', PageGridController::class, 'update');
        $router->delete('/page-grids/{id}', PageGridController::class, 'destroy');
        $router->get('/page-grids/{id}/history', [PageGridController::class, 'history']);

        // page grids
        $router->post('/page-grids/{id}/restore', PageGridController::class, 'restore');
        $router->delete('/page-grids/{id}/force', PageGridController::class, 'forceDestroy');
        $router->post('/page-grids/{id}/duplicate', PageGridController::class, 'duplicate');
        $router->post('/page-grids/{id}/toggle-active', PageGridController::class, 'toggleActive');

        $router->post('/page-grids/{id}/pages', PageGridController::class, 'addPage');
        $router->delete('/page-grids/{id}/pages/{pageIndex}', PageGridController::class, 'removePage');
        $router->put('/page-grids/{id}/pages/{pageIndex}', PageGridController::class, 'updatePage');
        $router->post('/page-grids/{id}/pages/reorder', PageGridController::class, 'reorderPages');

        // Sites
        $router->get('/sites', [SiteController::class, 'index']);
        $router->get('/sites/current', [SiteController::class, 'getCurrent']);
        $router->get('/sites/{id}', [SiteController::class, 'show']);
        $router->post('/sites', [SiteController::class, 'create']);
        $router->put('/sites/{id}', [SiteController::class, 'update']);
        $router->put('/sites/current', [SiteController::class, 'updateCurrent']);
        $router->delete('/sites/{id}', [SiteController::class, 'delete']);

// Contact Info Routes
        $router->get('/sites/contact', [SiteController::class, 'getContactInfo']);
        $router->put('/sites/contact', [SiteController::class, 'updateContactInfo']);

// Social Media Routes
        $router->put('/sites/social', [SiteController::class, 'updateSocialMedia']);

// Branding Routes
        $router->post('/sites/logo', [SiteController::class, 'uploadLogo']);
        $router->post('/sites/favicon', [SiteController::class, 'uploadFavicon']);

// Settings Routes
        $router->put('/sites/settings', [SiteController::class, 'updateSettings']);

// Status Routes
        $router->put('/sites/{id}/status', [SiteController::class, 'toggleStatus']);

        // Region Sets
        $router->get('/region-sets', [RegionSetController::class, 'index']);
        $router->post('/region-sets', [RegionSetController::class, 'store']);
        $router->get('/region-sets/active', [RegionSetController::class, 'getActive']);
        $router->post('/region-sets/reorder', [RegionSetController::class, 'reorder']);
        $router->get('/region-sets/{id}', [RegionSetController::class, 'show']);
        $router->put('/region-sets/{id}', [RegionSetController::class, 'update']);
        $router->delete('/region-sets/{id}', [RegionSetController::class, 'destroy']);
        $router->get('/region-sets/{id}/check-deletable', [RegionSetController::class, 'checkDeletable']);
        $router->get('/region-sets/{id}/alternatives', [RegionSetController::class, 'getAlternatives']);
        $router->post('/region-sets/{id}/duplicate', [RegionSetController::class, 'duplicate']);

// Territories
        $router->get('/territories', [TerritoryController::class, 'index']);
        $router->post('/territories', [TerritoryController::class, 'store']);
        $router->get('/territories/active', [TerritoryController::class, 'getActive']);
        $router->post('/territories/reorder', [TerritoryController::class, 'reorder']);
        $router->post('/territories/bulk-update-region-set', [TerritoryController::class, 'bulkUpdateRegionSet']);
        $router->get('/territories/by-region-set/{regionSetId}', [TerritoryController::class, 'getByRegionSet']);
        $router->get('/territories/{id}', [TerritoryController::class, 'show']);
        $router->put('/territories/{id}', [TerritoryController::class, 'update']);
        $router->delete('/territories/{id}', [TerritoryController::class, 'destroy']);
        $router->get('/territories/{id}/check-deletable', [TerritoryController::class, 'checkDeletable']);
        $router->get('/territories/{id}/alternatives', [TerritoryController::class, 'getAlternatives']);

        $router->get('/region-sets/{id}/pages', [RegionSetController::class, 'getPages']);
        $router->get('/region-sets/{id}/search-pages', [RegionSetController::class, 'searchAvailablePages']);
        $router->post('/region-sets/{id}/assign-pages', [RegionSetController::class, 'assignPages']);
        $router->post('/region-sets/{id}/unassign-pages', [RegionSetController::class, 'unassignPages']);

// Territory Pages
        $router->get('/territories/{id}/pages', [TerritoryController::class, 'getPages']);
        $router->get('/territories/{id}/search-pages', [TerritoryController::class, 'searchAvailablePages']);
        $router->post('/territories/{id}/assign-pages', [TerritoryController::class, 'assignPages']);
        $router->post('/territories/{id}/unassign-pages', [TerritoryController::class, 'unassignPages']);

        // Search
        $router->get('/search/pages', SearchController::class, 'pages');
        $router->get('/search/categories', SearchController::class, 'categories');

        $router->get('/vouchers', VoucherController::class, 'index');
        $router->post('/vouchers', VoucherController::class, 'store');
        $router->get('/vouchers/active', VoucherController::class, 'active');
        $router->get('/vouchers/{id}', VoucherController::class, 'show');
        $router->put('/vouchers/{id}', VoucherController::class, 'update');
        $router->delete('/vouchers/{id}', VoucherController::class, 'destroy');
        $router->get('/vouchers/{id}/check-delete', VoucherController::class, 'checkDelete');
        $router->get('/vouchers/{id}/alternatives', VoucherController::class, 'alternatives');
        $router->post('/vouchers/{id}/duplicate', VoucherController::class, 'duplicate');
        $router->post('/vouchers/validate', VoucherController::class, 'validate');
        $router->post('/vouchers/{id}/apply', VoucherController::class, 'apply');

         $router->post('/newsletter/signup', NewsletterController::class, 'signup');
         $router->post('/newsletter/confirm', NewsletterController::class, 'confirm');
         $router->post('/newsletter/unsubscribe', NewsletterController::class, 'unsubscribe');
         $router->get('/newsletter/subscribers', NewsletterController::class, 'getSubscribers');
    });

    $router->post('/sites', [SiteController::class, 'create']);
});

$router->post('/api/{siteName}/newsletter/web/signup', NewsletterController::class, 'signup');


$router->get('/api/pages/{pageId}/custom-fields', CustomFieldDefinitionController::class, 'getCustomFields');

$router->put('/api/pages/{pageId}/custom-fields', CustomFieldDefinitionController::class, 'updateCustomFields');;


// Blocks API
$router->get('/api/blocks/{id}', BlockController::class, 'show');
$router->put('/api/blocks/{id}', BlockController::class, 'update');
$router->delete('/api/blocks/{id}', BlockController::class, 'destroy');
$router->get('/api/blocks/type/{type}', BlockController::class, 'getByType');
$router->get('/api/search-properties', EstateWebsiteController::class, 'search');


// Menu items
$router->get('/api/menu-items', MenuItemController::class, 'index');
$router->post('/api/menu-items', MenuItemController::class, 'store');
$router->get('/api/menu-items/{id}', MenuItemController::class, 'show');
$router->put('/api/menu-items/{id}', MenuItemController::class, 'update');
$router->delete('/api/menu-items/{id}', MenuItemController::class, 'destroy');
$router->post('/api/menu-items/reorder', MenuItemController::class, 'reorder');


// Author public view route
$router->get('/authors/{slug}', 'AuthorViewController@show');

//Auth
$router->post('/api/{siteName}/auth/login', AuthController::class, 'login');
$router->get('/api/sites', SiteController::class, 'index');

$router->get('/api/{site}/cart', CartController::class, 'index');
$router->post('/api/{site}/cart/add', CartController::class, 'add');
$router->put('/api/{site}/cart/update/{id}', CartController::class, 'update');
$router->delete('/api/{site}/cart/remove/{id}', CartController::class, 'remove');
$router->post('/api/{site}/cart/clear', CartController::class, 'clear');

$router->get('/api/{site}/wishlist', WishlistController::class, 'index');
$router->post('/api/{site}/wishlist/add', WishlistController::class, 'add');
$router->delete('/api/{site}/wishlist/remove/{productId}', WishlistController::class, 'remove');


// Product routes
$router->get('/api/{site}/product-list/search', ProductListController::class, 'search');;

//reviews
$router->get('/api/{site}/products/{productId}/reviews', ReviewController::class, 'index');
$router->post('/api/{site}/products/{productId}/reviews', ReviewController::class, 'store');
$router->put('/api/{site}/reviews/{reviewId}', ReviewController::class, 'update');
$router->delete('/api/{site}/reviews/{reviewId}', ReviewController::class, 'destroy');
$router->post('/api/{site}/reviews/{reviewId}/helpful', ReviewController::class, 'markHelpful');
$router->get('/api/{site}/products/{productId}/reviews/statistics', ReviewController::class, 'statistics');
$router->get('/api/{site}/products/{productId}/reviews/can-review', ReviewController::class, 'canReview');

// Video routes
$router->get('/api/{site}/videos', VideoController::class, 'index');
$router->post('/api/{site}/videos', VideoController::class, 'upload');
$router->get('/api/{site}/videos/{id}', VideoController::class, 'show');
$router->delete('/api/{site}/videos/{id}', VideoController::class, 'delete');

$router->post('/api/preview', [PreviewController::class, 'preview']);

//comments
$router->post('/comments', [CommentController::class, 'store']);
$router->put('/comments/{commentId}/moderate', [CommentController::class, 'moderate']);
$router->get('/pages/{pageId}/comments', [CommentController::class, 'index']);
$router->delete('/comments/{commentId}', [CommentController::class, 'destroy']);



