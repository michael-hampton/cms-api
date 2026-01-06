<?php

namespace App\Framework\Authorization;

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
}