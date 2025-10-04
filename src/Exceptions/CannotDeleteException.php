<?php

namespace App\Exceptions;

use Exception;

class CannotDeleteException extends Exception
{
    protected $relatedCount;
    protected $entityType;

    public function __construct(string $entityType, int $relatedCount)
    {
        $this->entityType = $entityType;
        $this->relatedCount = $relatedCount;

        parent::__construct(
            "Cannot delete {$entityType}. It has {$relatedCount} associated page(s). " .
            "Please reassign or delete the associated pages first."
        );
    }

    public function getRelatedCount(): int
    {
        return $this->relatedCount;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }
}