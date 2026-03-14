<?php

namespace App\Enums;

enum BriefStatus: string
{
    case DRAFT = 'draft';
    case INACTIVE = 'inactive';
    case TRASH = 'trash';
}