<?php

declare(strict_types=1);

namespace App\Requests\Subscription;

class PrintVendorConnectionStoreRequest extends BasePrintVendorConnectionRequest
{
    protected function isCreate(): bool
    {
        return true;
    }
}