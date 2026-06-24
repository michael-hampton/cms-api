<?php

use App\Controllers\AuthController;
use App\Framework\Http\Router;

/**
 * @var $router Router
 */
$router->post('/api/auth/login', [AuthController::class, 'globalLogin']);
