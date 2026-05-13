<?php

namespace App\Events\OpenCollab;

use App\Models\Contract;

final class ContractDraftCreatedEvent
{
    public function __construct(
        public readonly Contract $contract,
        public readonly int      $siteId,
        public readonly ?int     $clonedFromContractId,
        public readonly ?int     $sourceTemplateId,
    )
    {
    }
}