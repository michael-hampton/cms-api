<?php

namespace App\Enums\Cms;

enum CustomFieldStorageType: string
{
    case CustomValue   = 'custom_value';
    case ProfileColumn = 'profile_column';
}