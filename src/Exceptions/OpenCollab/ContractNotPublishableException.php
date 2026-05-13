<?php

namespace App\Exceptions\OpenCollab;

use RuntimeException;

final class ContractNotPublishableException extends RuntimeException
{
    public static function notDraft(int $contractId, string $currentStatus): self
    {
        return new self("Contract #{$contractId} cannot be published because its status is '{$currentStatus}'.");
    }
}