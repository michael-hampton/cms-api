<?php

namespace App\Enums\Subscriptions;

enum LetterBatchStatus: string
{
    case PENDING  = 'pending';
    case EXPORTED = 'exported';
    case FAILED   = 'failed';
}
