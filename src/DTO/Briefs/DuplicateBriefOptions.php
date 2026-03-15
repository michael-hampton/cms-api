<?php

namespace App\DTO\Briefs;

final class DuplicateBriefOptions
{
    public function __construct(
        public readonly bool $includeSubtasks = true,
        public readonly bool $includeCollaborators = true,
        public readonly bool $includeComments = true,
        public readonly bool $includeRelationships = true,
        public readonly bool $includeDeadlines = true,
    )
    {
    }

    public static function all(): self
    {
        return new self();
    }

    public static function coreOnly(): self
    {
        return new self(
            includeSubtasks: false,
            includeCollaborators: false,
            includeComments: false,
            includeRelationships: false,
            includeDeadlines: false,
        );
    }
}