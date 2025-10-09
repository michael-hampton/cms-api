<?php

namespace App\Framework\Middleware;

use App\Framework\Session\Session;

class SessionMiddleware
{
    /**
     * Handle the request and ensure session is started
     */
    public function handle($request, $next)
    {
        // Start session if not already started
        Session::start();

        // Initialize cart and wishlist session IDs if they don't exist
        if (!Session::has('cart_session_id')) {
            Session::put('cart_session_id', uniqid('cart_', true));
        }

        if (!Session::has('wishlist_session_id')) {
            Session::put('wishlist_session_id', uniqid('wishlist_', true));
        }

        $response = $next($request);

        // Age flash data at the end of the request
        Session::ageFlashData();

        return $response;
    }
}