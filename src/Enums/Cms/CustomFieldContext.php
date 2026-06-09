<?php

namespace App\Enums\Cms;

enum CustomFieldContext: string
{
    case Page               = 'page';
    case ContributorProfile = 'contributor_profile';
}