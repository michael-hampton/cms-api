<?php

namespace App\Events\OpenCollab;

use App\Models\Contract;

final class ContractArchivedEvent
{
    public function __construct(
        public readonly Contract $contract,
        public readonly int      $siteId,
        public readonly int      $archivedByUserId,
    )
    {
    }
}