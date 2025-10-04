<?php
// API routes (return arrays -> converted to JSON)
use App\Controllers\AuthorController;
use App\Controllers\BlockController;
use App\Controllers\CategoryController;
use App\Controllers\CustomFieldDefinitionController;
use App\Controllers\EstateWebsiteController;
use App\Controllers\ImageController;
use App\Controllers\MenuController;
use App\Controllers\MenuItemController;
use App\Controllers\PageController;
use App\Controllers\ProductController;
use App\Controllers\SearchController;
use App\Controllers\TagController;
use App\Framework\Container;
use App\Framework\Http\Router;

// Pages API
$router->get('/api/pages', PageController::class, 'index');
$router->post('/api/pages', PageController::class, 'store');
$router->post('/api/pages/bulk-update', PageController::class, 'bulkUpdate');
$router->get('/api/pages/block-types', PageController::class, 'getAvailableBlockTypes');
$router->get('/api/pages/{id}', PageController::class, 'show');
$router->put('/api/pages/{id}', PageController::class, 'update');
$router->delete('/api/pages/{id}', PageController::class, 'destroy');
$router->get('/api/block-types', PageController::class, 'getAvailableBlockTypes');
$router->post('/api/pages/{id}/duplicate', PageController::class, 'duplicate');
$router->get('/api/featured-pages', PageController::class, 'getFeaturedPages');

$router->get('/api/pages/{pageId}/custom-fields', CustomFieldDefinitionController::class, 'getCustomFields');
$router->get('/api/pages/{pageId}/custom-fields/grouped', CustomFieldDefinitionController::class, 'getCustomFieldsGrouped');;
$router->put('/api/pages/{pageId}/custom-fields', CustomFieldDefinitionController::class, 'updateCustomFields');;



// Categories API
$router->get('/api/categories', CategoryController::class, 'index');
$router->post('/api/categories', CategoryController::class, 'store');
$router->get('/api/categories/{id}', CategoryController::class, 'show');
$router->put('/api/categories/{id}', CategoryController::class, 'update');
$router->delete('/api/categories/{id}', CategoryController::class, 'destroy');
$router->get('/api/categories/{id}/check-delete', CategoryController::class, 'checkDelete');

// Tags API
$router->get('/api/tags', TagController::class, 'index');
$router->get('/api/tags/cloud', TagController::class, 'cloud');
$router->post('/api/tags', TagController::class, 'store');
$router->get('/api/tags/{id}', TagController::class, 'show');
$router->put('/api/tags/{id}', TagController::class, 'update');
$router->delete('/api/tags/{id}', TagController::class, 'destroy');
$router->post('/api/tags/cleanup', TagController::class, 'cleanup');
$router->get('/api/featured-tags', TagController::class, 'featured');
$router->get('/api/popular-tags', TagController::class, 'popular');
$router->get('/api/tags/{id}/check-delete', TagController::class, 'checkDelete');


// Custom Fields API
$router->get('/api/custom-fields', CustomFieldDefinitionController::class, 'index');
$router->get('/api/custom-fields/grouped', CustomFieldDefinitionController::class, 'grouped');
$router->get('/api/custom-fields/required', CustomFieldDefinitionController::class, 'required');
$router->get('/api/custom-fields/searchable', CustomFieldDefinitionController::class, 'searchable');
$router->post('/api/custom-fields', CustomFieldDefinitionController::class, 'store');
$router->get('/api/custom-fields/{id}', CustomFieldDefinitionController::class, 'show');
$router->put('/api/custom-fields/{id}', CustomFieldDefinitionController::class, 'update');
$router->delete('/api/custom-fields/{id}', CustomFieldDefinitionController::class, 'destroy');

// Blocks API
$router->get('/api/blocks/{id}', BlockController::class, 'show');
$router->put('/api/blocks/{id}', BlockController::class, 'update');
$router->delete('/api/blocks/{id}', BlockController::class, 'destroy');
$router->get('/api/blocks/type/{type}', BlockController::class, 'getByType');
$router->get('/api/search-properties', EstateWebsiteController::class, 'search');

// Menu
$router->get('/api/menu', MenuController::class, 'index');
$router->post('/api/menu', MenuController::class, 'store');
$router->get('/api/menu/{id}', MenuController::class, 'show');
$router->get('/api/menu/{id}/hierarchy', MenuController::class, 'hierarchy');
$router->get('/api/menu/slug/{slug}', MenuController::class, 'getMenuBySlug');
$router->put('/api/menu/{id}', MenuController::class, 'update');
$router->delete('/api/menu/{id}', MenuController::class, 'destroy');

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

// Images
$router->get('/api/images', ImageController::class, 'index');
$router->post('/api/images', ImageController::class, 'store');
$router->get('/api/images/{id}', ImageController::class, 'show');
$router->put('/api/images/{id}', ImageController::class, 'update');
$router->delete('/api/images/{id}', ImageController::class, 'destroy');

// Bulk operations
$router->delete('/api/images/bulk', ImageController::class, 'bulkDestroy');

// Utility routes
$router->get('/api/image-recent', ImageController::class, 'recent');
$router->get('/api/images/statistics', ImageController::class, 'statistics');
$router->get('/api/images/unused', ImageController::class, 'unused');
$router->post('/api/images/cleanup', ImageController::class, 'cleanup');

// Usage tracking
$router->post('/api/image-track-usage', ImageController::class, 'trackUsage');
$router->post('/api/image-remove-usage', ImageController::class, 'removeUsage');

// Category management
$router->get('/api/image-categories', ImageController::class, 'categories');
$router->post('/api/image-categories', ImageController::class, 'createCategory');

// Author API Routes
$router->get('/api/authors', AuthorController::class, 'index');
$router->get('/api/authors/active', AuthorController::class, 'getActive');
$router->post('/api/authors', AuthorController::class, 'store');
$router->post('/api/authors/merge', AuthorController::class, 'merge');
$router->get('/api/authors/{id}', AuthorController::class, 'show');
$router->put('/api/authors/{id}', AuthorController::class, 'update');
$router->delete('/api/authors/{id}', AuthorController::class, 'destroy');
$router->get('/api/authors/{id}/check-delete', AuthorController::class, 'checkDelete');

//products
$router->get('/api/products', ProductController::class, 'index');
$router->post('/api/products', ProductController::class, 'store');
$router->get('/api/products/{id}', ProductController::class, 'show');
$router->put('/api/products/{id}', ProductController::class, 'update');
$router->delete('/api/products/{id}', ProductController::class, 'destroy');

// Author public view route
$router->get('/authors/{slug}', 'AuthorViewController@show');
