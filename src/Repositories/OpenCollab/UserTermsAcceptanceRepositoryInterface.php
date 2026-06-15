<?php

namespace App\Repositories\OpenCollab;

use App\Models\UserTermsAcceptance;

interface UserTermsAcceptanceRepositoryInterface
{
    public function findWithTermsVersion(int $id): ?UserTermsAcceptance;
}
