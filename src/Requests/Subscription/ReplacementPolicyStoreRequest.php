<?php

declare(strict_types=1);

namespace App\Requests\Subscription;

class ReplacementPolicyStoreRequest extends BaseReplacementPolicyRequest
{
    protected function isCreate(): bool
    {
        return true;
    }
}