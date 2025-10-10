<?php
// API routes (return arrays -> converted to JSON)
use App\Controllers\AuthController;
use App\Controllers\AuthorController;
use App\Controllers\BlockController;
use App\Controllers\BrandController;
use App\Controllers\CartController;
use App\Controllers\CategoryController;
use App\Controllers\CustomFieldDefinitionController;
use App\Controllers\EstateWebsiteController;
use App\Controllers\ImageController;
use App\Controllers\MenuController;
use App\Controllers\MenuItemController;
use App\Controllers\PageController;
use App\Controllers\PageHistoryController;
use App\Controllers\ProductController;
use App\Controllers\ProductListController;
use App\Controllers\ReviewController;
use App\Controllers\SearchController;
use App\Controllers\SiteController;
use App\Controllers\TagController;
use App\Controllers\UserController;
use App\Controllers\VideoController;
use App\Controllers\WishlistController;
use App\Framework\Authorization\AuthenticateWithToken;

$router->group(['prefix' => 'api', 'middleware' => AuthenticateWithToken::class], function ($router) {
    // Pages API
    $router->group(['prefix' => '{siteName}'], function ($router) {
        $router->get('/pages', PageController::class, 'index');
        $router->post('/pages', PageController::class, 'store', [AuthenticateWithToken::class]);
        $router->post('/pages/bulk-update', PageController::class, 'bulkUpdate', [AuthenticateWithToken::class]);
        $router->get('/pages/block-types', PageController::class, 'getAvailableBlockTypes', [AuthenticateWithToken::class]);
        $router->get('/pages/{id}', PageController::class, 'show');
        $router->put('/pages/{id}', PageController::class, 'update');
        $router->delete('/pages/{id}', PageController::class, 'destroy');
        $router->get('/block-types', PageController::class, 'getAvailableBlockTypes');
        $router->post('/pages/{id}/duplicate', PageController::class, 'duplicate');
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

        $router->get('/users', UserController::class, 'index');
        $router->post('/users', UserController::class, 'store');
        $router->get('/users/{id}', UserController::class, 'show');
        $router->put('/users/{id}', UserController::class, 'update');
        $router->delete('/users/{id}', UserController::class, 'destroy');


        // Page History routes
        $router->get('/pages/{pageId}/history', PageHistoryController::class, 'index');
        $router->get('/history/{id}',  PageHistoryController::class, 'show');
        $router->get('/history/recent',  PageHistoryController::class, 'recent');
        $router->get('/users/{userId}/history',  PageHistoryController::class, 'userHistory');
        $router->post('/history/{historyId}/restore',  PageHistoryController::class, 'restore');


//        Route::prefix('page-grids')->group(function () {
//            Route::get('/', [PageGridController::class, 'index']);
//            Route::post('/', [PageGridController::class, 'store']);
//            Route::get('/slug/{slug}', [PageGridController::class, 'showBySlug']);
//            Route::get('/{id}', [PageGridController::class, 'show']);
//            Route::put('/{id}', [PageGridController::class, 'update']);
//            Route::delete('/{id}', [PageGridController::class, 'destroy']);
//
//            // Additional actions
//            Route::post('/{id}/restore', [PageGridController::class, 'restore']);
//            Route::delete('/{id}/force', [PageGridController::class, 'forceDestroy']);
//            Route::post('/{id}/duplicate', [PageGridController::class, 'duplicate']);
//            Route::post('/{id}/toggle-active', [PageGridController::class, 'toggleActive']);
//
//            // Page management within grid
//            Route::post('/{id}/pages', [PageGridController::class, 'addPage']);
//            Route::delete('/{id}/pages/{pageIndex}', [PageGridController::class, 'removePage']);
//            Route::put('/{id}/pages/{pageIndex}', [PageGridController::class, 'updatePage']);
//            Route::post('/{id}/pages/reorder', [PageGridController::class, 'reorderPages']);

    });
});


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

// Search
$router->get('/api/search/pages', SearchController::class, 'pages');
$router->get('/api/search/categories', SearchController::class, 'categories');


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

