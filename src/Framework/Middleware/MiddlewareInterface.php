<?php

namespace App\Framework\Middleware;

use App\Framework\Http\Request;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next);
}