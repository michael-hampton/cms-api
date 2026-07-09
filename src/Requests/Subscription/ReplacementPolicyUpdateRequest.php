<?php

declare(strict_types=1);

namespace App\Requests\Subscription;

class ReplacementPolicyUpdateRequest extends BaseReplacementPolicyRequest
{
    protected function isCreate(): bool
    {
        return false;
    }
}