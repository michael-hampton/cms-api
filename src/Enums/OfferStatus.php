<?php

namespace App\Enums;

enum OfferStatus: string
{
    case PENDING = 'pending';
    case PUBLISHED = 'published';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    const DRAFT = 'draft';
}