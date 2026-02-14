<?php

namespace App\Enums;

enum ConsentAction: string
{
    case GRANTED = 'granted';
    case REVOKED = 'revoked';
    case EXPIRED = 'expired';
    case UPDATED = 'updated';
}