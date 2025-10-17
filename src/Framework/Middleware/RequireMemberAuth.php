<?php

namespace App\Framework\Middleware;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Http\Response;

class RequireMemberAuth
{
    public function handle(Request $request, callable $next): Response
    {
        if (!MemberAuth::check()) {
            // Store intended URL
            $request->session()->put('intended_url', $request->getUri());

            // Redirect to login
            return new Response('', 302, ['Location' => '/member/login']);
        }

        return $next($request);
    }
}