<?php

declare(strict_types=1);

namespace App\Requests\Subscription;

class PrintVendorConnectionUpdateRequest extends BasePrintVendorConnectionRequest
{
    protected function isCreate(): bool
    {
        return false;
    }
}