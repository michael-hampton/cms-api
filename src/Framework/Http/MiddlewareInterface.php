<?php

namespace App\Framework\Http;

interface MiddlewareInterface
{
    /**
     * Handle an incoming request
     *
     * @param Request $request
     * @param callable $next
     * @return Response|mixed
     */
    public function handle(Request $request, callable $next);
}