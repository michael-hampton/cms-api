<?php

namespace App\Enums\Billing;

enum WebhookEventStatus: string
{
    case PROCESSED = 'processed';
    case IGNORED = 'ignored';
    case FAILED = 'failed';
}
