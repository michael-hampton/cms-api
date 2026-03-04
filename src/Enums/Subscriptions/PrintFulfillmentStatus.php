<?php

namespace App\Enums\Subscriptions;

enum PrintFulfillmentStatus: string
{
    case QUEUED = 'queued';
    case EXPORTED = 'exported';
    case SENT_TO_PRINTER = 'sent_to_printer';
    case SHIPPED = 'shipped';
    case IN_TRANSIT = 'in_transit';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
}