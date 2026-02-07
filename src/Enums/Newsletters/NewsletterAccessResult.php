<?php

namespace App\Enums\Newsletters;

enum NewsletterAccessResult: string
{
    case FREE = 'free';
    case SUBSCRIPTION = 'subscription';
    case NO_SUBSCRIPTION = 'no_subscription';
    case INSUFFICIENT_LEVEL = 'insufficient_access_level';
    case AUTHENTICATION_REQUIRED = 'authentication_required';
    case NOT_FOUND = 'not_found';
}