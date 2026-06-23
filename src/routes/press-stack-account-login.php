<?php

use App\Controllers\Subscription\ShopAccountController;
use App\Framework\Http\Router;
use App\Framework\Middleware\VerifyCsrfToken;

/** @var Router $router */
$router->post('/press-stack/account/login', [ShopAccountController::class, 'loginWithEmail'], middleware: [VerifyCsrfToken::class]);
