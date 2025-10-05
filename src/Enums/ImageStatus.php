<?php

namespace App\Enums;

enum ImageStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case TRASH = 'trash';
}