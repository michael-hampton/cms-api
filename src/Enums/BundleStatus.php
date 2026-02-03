<?php

namespace App\Enums;

enum BundleStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
}