<?php

namespace App\Exceptions\OpenCollab;

use RuntimeException;

final class ContractNotEditableException extends RuntimeException
{
    public static function alreadyPublished(int $contractId): self
    {
        return new self("Contract #{$contractId} is published and cannot be edited. Create a new version instead.");
    }

    public static function alreadyArchived(int $contractId): self
    {
        return new self("Contract #{$contractId} is archived and cannot be edited.");
    }
}