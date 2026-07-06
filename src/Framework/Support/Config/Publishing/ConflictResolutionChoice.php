<?php

namespace App\Framework\Support\Config\Publishing;

/**
 * The user's explicit decision for one conflicting key. Conflicting
 * keys are never auto-merged (Ticket 5) — a choice must be supplied per
 * key, or the key is simply left unpublished (latest's value stands,
 * the user's local edit stays local).
 */
final class ConflictResolutionChoice
{
    private function __construct(
        public readonly ConflictChoiceType $type,
        public readonly bool $exists = true,
        public readonly mixed $value = null,
    ) {
    }

    public static function keepMine(): self
    {
        return new self(ConflictChoiceType::KeepMine);
    }

    public static function keepTheirs(): self
    {
        return new self(ConflictChoiceType::KeepTheirs);
    }

    public static function edited(mixed $value): self
    {
        return new self(ConflictChoiceType::Edited, exists: true, value: $value);
    }

    /**
     * An explicit "delete this key" edit, distinct from editing it to a
     * value — needed so clearing/deleting during conflict resolution is
     * preserved correctly rather than being indistinguishable from "no
     * change".
     */
    public static function editedDelete(): self
    {
        return new self(ConflictChoiceType::Edited, exists: false, value: null);
    }
}