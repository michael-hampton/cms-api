<?php

namespace App\Repositories\OpenCollab;

use App\Models\UserTermsAcceptance;
use App\Repositories\Repository;

class UserTermsAcceptanceRepository extends Repository implements UserTermsAcceptanceRepositoryInterface
{
    public function findWithTermsVersion(int $id): ?UserTermsAcceptance
    {
        return UserTermsAcceptance::with(['termsVersion'])
            ->where('id', $id)
            ->first();
    }

    protected function getModelClass(): string
    {
        return UserTermsAcceptance::class;
    }
}
