<?php

namespace App\Enums\Offers;

enum OfferStatus: string
{
    case PENDING = 'pending';
    case PUBLISHED = 'published';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    const DRAFT = 'draft';
}