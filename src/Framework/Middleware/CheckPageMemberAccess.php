<?php

namespace App\Framework\Middleware;

use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Models\Page;

class CheckPageMemberAccess
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        $page = $request->getAttribute('page');

        if (!$page instanceof Page) {
            return $response;
        }

        $needsLogin = (new \App\Parsers\Url\CheckPageMemberAccess())->handle($request, $page);

        if ($needsLogin) {
            $message = $page->non_member_message ?? 'This content requires a member login.';
            return new Response(accessDenied($message), 403);
        }

        return $response;
    }
}