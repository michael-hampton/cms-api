<?php

namespace App\Services\Auth;

use App\Framework\Authorization\MemberAuth;
use App\Models\Member;
use App\Services\Auth\Contracts\AuthenticatedMemberResolverInterface;

/**
 * Thin adapter around the MemberAuth static facade. This is the one place
 * that static coupling is allowed to live - every other class depends on
 * AuthenticatedMemberResolverInterface instead, so it can be swapped for a
 * Mockery double in tests without touching session/DB state.
 */
class SessionAuthenticatedMemberResolver implements AuthenticatedMemberResolverInterface
{
    public function check(): bool
    {
        return MemberAuth::check();
    }

    public function resolve(): ?Member
    {
        if (!MemberAuth::check()) {
            return null;
        }

        return MemberAuth::getMember();
    }
}
