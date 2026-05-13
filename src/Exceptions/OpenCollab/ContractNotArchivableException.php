<?php

namespace App\Exceptions\OpenCollab;

use RuntimeException;

final class ContractNotArchivableException extends RuntimeException
{
    public static function notPublished(int $contractId, string $currentStatus): self
    {
        return new self("Contract #{$contractId} cannot be archived because its status is '{$currentStatus}'.");
    }
}