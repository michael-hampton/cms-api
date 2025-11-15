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

    public function memberId()
    {
        return MemberAuth::id();
    }
}