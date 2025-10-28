<?php

namespace App\Repositories;

use App\Models\Member;

class MemberRepository extends Repository
{

    protected function getModelClass(): string
    {
        return Member::class;
    }

    public function findByEmail(string $email): ?Member
    {
        return $this->where('email', $email)->first();
    }
}