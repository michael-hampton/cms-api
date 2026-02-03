<?php

namespace App\Services\Rewards\Handlers;

use App\Models\Member;
use App\Models\RewardDefinition;

interface RewardTypeHandlerInterface
{
    public function handle(Member $member, RewardDefinition $definition, int $siteId): ?array;
}