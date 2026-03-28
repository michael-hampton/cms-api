<?php

declare(strict_types=1);

namespace App\Enums\Products;

enum ProductBatchStatus: string
{
    case QUEUED = 'queued';
    case EXPORTING = 'exporting';
    case EXPORTED = 'exported';
    case FAILED = 'failed';
}