<?php

namespace App\Enums\OpenCollab;

enum RiskSource: string
{
    case CreatorDeclaration = 'creator_declaration';
    case AutomatedCheck = 'automated_check';
    case Moderator = 'moderator';
    case Legal = 'legal';
    case BrandSafety = 'brand_safety';
}