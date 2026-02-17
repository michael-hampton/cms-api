<?php

namespace App\Framework\Authorization;

use App\Models\Member;

class MemberAuthWrapper
{

    public function check()
    {
        return MemberAuth::check();
    }

    public function member()
    {
        return MemberAuth::member();
    }

    public function getMember()
    {
        return MemberAuth::getMember();
    }

    public function memberId()
    {
        return MemberAuth::id();
    }

    public function login(Member $member): void
    {
        MemberAuth::login($member);
    }
}