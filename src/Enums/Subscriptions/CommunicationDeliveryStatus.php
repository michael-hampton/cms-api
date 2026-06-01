<?php

namespace App\Enums\Subscriptions;

enum CommunicationDeliveryStatus: string
{
    case PENDING   = 'pending';
    case SENT      = 'sent';
    case FAILED    = 'failed';
    case SKIPPED   = 'skipped';
    case CANCELLED = 'cancelled';
}