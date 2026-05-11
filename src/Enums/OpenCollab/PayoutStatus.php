<?php

namespace App\Enums\OpenCollab;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Failed = 'failed';
    case Rejected = 'rejected';
}