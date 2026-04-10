<?php

namespace App\Enums\OpenCollab;

enum InvitationStatus: string
{
    case Pending = 'pending';
    case Used = 'used';
    case Expired = 'expired';
    case Revoked = 'revoked';
}