<?php

namespace App\Enums\OpenCollab;

enum PayoutAuditAction: string
{
    case Approved = 'approved';
    case Declined = 'declined';
    case Paid = 'paid';
}