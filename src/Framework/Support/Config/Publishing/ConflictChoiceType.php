<?php

namespace App\Framework\Support\Config\Publishing;

enum ConflictChoiceType: string
{
    case KeepMine = 'keep_mine';
    case KeepTheirs = 'keep_theirs';
    case Edited = 'edited';
}