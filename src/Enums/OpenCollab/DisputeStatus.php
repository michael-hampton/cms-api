<?php

namespace App\Enums\OpenCollab;

enum DisputeStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
}