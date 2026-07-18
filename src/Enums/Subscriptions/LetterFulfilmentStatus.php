<?php

namespace App\Enums\Subscriptions;

enum LetterFulfilmentStatus: string
{
    case PENDING  = 'pending';
    case EXPORTED = 'exported';
    case FAILED   = 'failed';
}
