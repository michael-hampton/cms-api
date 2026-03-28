<?php

declare(strict_types=1);

namespace App\Enums\Products;

enum ProductFulfilmentStatus: string
{
    case QUEUED = 'queued';
    case EXPORTED = 'exported';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
}