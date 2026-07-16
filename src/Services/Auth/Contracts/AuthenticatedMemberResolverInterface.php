<?php

namespace App\Services\Auth\Contracts;

use App\Models\Member;

/**
 * Resolves the currently-authenticated member for the active request.
 *
 * Controllers depend on this interface instead of calling the MemberAuth
 * static facade directly, so authentication can be mocked with Mockery in
 * unit tests (per the "no static coupling" testing rule) rather than
 * requiring a real session/DB round trip.
 */
interface AuthenticatedMemberResolverInterface
{
    public function check(): bool;

    public function resolve(): ?Member;
}
