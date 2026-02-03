<?php

namespace App\Enums;

enum OfferAction: string
{
    case VIEW = 'view';
    case CLICK = 'click';
    case COPY_CODE = 'copy_code';
}