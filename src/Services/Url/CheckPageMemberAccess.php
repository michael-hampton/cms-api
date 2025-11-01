<?php

namespace App\Services\Url;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Models\Page;

class CheckPageMemberAccess
{

    public function handle(Request $request, Page $page): bool
    {

        // Check if page requires member login
        if (!$page->metadata || $page->metadata->visibility === 'public') { //todo enum
            return false;
        }

        // Check if member is authenticated
        if (!MemberAuth::check()) {
            $request->session()->put('intended_url', $request->getUri());

            return true;
        }

        // Check role requirements
        $allowedRoles = $page->allowed_member_roles
            ? json_decode($page->allowed_member_roles, true)
            : null;

        if ($allowedRoles && !empty($allowedRoles)) {
            $member = MemberAuth::member();

            if (!$member->hasAnyRole($allowedRoles)) {
                return true;
            }
        }

        return false;
    }
}