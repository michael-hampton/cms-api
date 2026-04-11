<?php

namespace App\Enums\OpenCollab;

enum ViolationAction: string
{
    case Warning = 'warning';
    case Suspension = 'suspension';
    case Ban = 'ban';
}