<?php

use App\Controllers\AuthorViewController;
use App\Controllers\BlockController;
use App\Controllers\CategoryPageController;
use App\Controllers\CommentController;
use App\Controllers\ContentController;
use App\Controllers\EstateWebsiteController;
use App\Controllers\EventController;
use App\Controllers\MemberAuthController;
use App\Controllers\ProductDetailController;
use App\Controllers\ProductListController;
use App\Controllers\RegionContentController;
use App\Controllers\TagViewController;
use App\Controllers\WebPageController;
use App\Framework\Container;
use App\Framework\Http\Router;
use App\Framework\Middleware\CheckPageMemberAccess;
use App\Framework\Middleware\RequireMemberAuth;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here are the API routes for your application. These routes are loaded
| by the RouteLoader within a group that is assigned the "api" prefix.
| All routes return JSON responses.
|
*/

// Pages API


// Web routes (return Response objects -> HTML)
$router->get('/', WebPageController::class, 'index');
$router->get('/pages', WebPageController::class, 'index');
$router->get('/pages/search', WebPageController::class, 'search');
$router->get('/test', WebPageController::class, 'test');
$router->get('/pages/create', WebPageController::class, 'create');
$router->post('/pages', WebPageController::class, 'store');
$router->get('/pages/{id}', WebPageController::class, 'show');
$router->get('/pages/{id}/edit', WebPageController::class, 'edit');
$router->put('/pages/{id}', WebPageController::class, 'update');
$router->delete('/pages/{id}', WebPageController::class, 'destroy');

// Block routes
$router->get('/blocks/{id}', BlockController::class, 'show');
$router->put('/blocks/{id}', BlockController::class, 'update');
$router->delete('/blocks/{id}', BlockController::class, 'destroy');
$router->get('/blocks/type/{type}', BlockController::class, 'getByType');

//$router->get('/estate', EstateWebsiteController::class, 'homepage');
//$router->get('/about', EstateWebsiteController::class, 'about');
//$router->get('/contact', EstateWebsiteController::class, 'contact');
$router->post('/contact', EstateWebsiteController::class, 'submitContact');
//$router->get('/properties', EstateWebsiteController::class, 'properties');
$router->get('/property/{id}', EstateWebsiteController::class, 'property');
$router->get('/category/{slug}', CategoryPageController::class, 'show');
$router->post('/event-signup', EventController::class, 'signup');
$router->post('/comments', CommentController::class, 'store');

$router->get('/authors/{slug}', AuthorViewController::class, 'show');
$router->get('/tags/{slug}', TagViewController::class, 'show');

$router->get('/shop', ProductListController::class, 'index');
$router->get('/shop/details/{slug}', ProductDetailController::class, 'show');
$router->get('/sites', ContentController::class, 'sites');

$router->get('/member/register', [MemberAuthController::class, 'showRegisterForm']);
$router->get('/member/login', [MemberAuthController::class, 'showLoginForm']);
$router->post('/member/logout', [MemberAuthController::class, 'logout']);

// Email verification routes
$router->get('/member/verify-email-sent', [MemberAuthController::class, 'showVerifyEmailSent']);
$router->get('/verify-email', [MemberAuthController::class, 'verifyEmail']);

// Password reset routes
$router->get('/member/forgot-password', [MemberAuthController::class, 'showForgotPasswordForm'])
    ->name('member.forgot-password');

$router->get('/member/reset-password', [MemberAuthController::class, 'showResetPasswordForm'])
    ->name('member.reset-password');



$router->group(['middleware' => [\App\Framework\Middleware\VerifyCsrfToken::class]], function($router) {
    $router->post('/member/forgot-password', [MemberAuthController::class, 'sendPasswordResetEmail'])
        ->name('member.forgot-password.send');

    $router->post('/member/reset-password', [MemberAuthController::class, 'resetPassword'])
        ->name('member.reset-password.update');

    $router->post('/member/login', [MemberAuthController::class, 'login'])
        ->name('member.login.submit');

    $router->post('/member/register', [MemberAuthController::class, 'register'])
        ->name('member.register');

    $router->post('/member/change-password', [MemberAuthController::class, 'changePassword'])
        ->name('member.change-password.update');
});

// Protected member routes
$router->group(['middleware' => [RequireMemberAuth::class]], function($router) {
    $router->get('/member/dashboard', [MemberAuthController::class, 'dashboard']);

    $router->get('/member/change-password', [MemberAuthController::class, 'showChangePasswordForm'])
        ->name('member.change-password');
});



// Apply page member access check to content routes
$router->get('/{slug}', [ContentController::class, 'show'])
    ->middleware([CheckPageMemberAccess::class]);

$router->get('/{siteName}/{regionSlug}/{pageSlug}', [RegionContentController::class, 'show'])
    ->middleware([CheckPageMemberAccess::class]);
    //->where('regionSlug', 'asia-pacific|europe|americas');