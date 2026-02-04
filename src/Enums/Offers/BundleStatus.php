<?php

namespace App\Enums\Offers;

enum BundleStatus: string
{
    case DRAFT = 'pending';
    case PUBLISHED = 'published';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
}