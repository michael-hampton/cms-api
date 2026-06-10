<?php

namespace App\ViewModels\OpenCollab;

use App\Framework\Support\Collection;

final readonly class ContributorRequestFormViewModel
{
    /**
     * @param array<int, ProfileFieldViewModel> $fields
     */
    public function __construct(
        public array $fields,
    ) {}

    public static function fromFields(Collection|array $fields): self
    {
        $fieldObjects = $fields instanceof Collection
            ? $fields->all()
            : $fields;

        return new self(
            fields: array_values(array_map(
                static fn($field) => ProfileFieldViewModel::fromDefinition($field, null),
                $fieldObjects,
            )),
        );
    }

    public function hasFields(): bool
    {
        return !empty($this->fields);
    }
}