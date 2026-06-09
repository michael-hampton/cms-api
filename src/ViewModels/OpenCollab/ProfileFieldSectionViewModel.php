<?php

namespace App\ViewModels\OpenCollab;

final readonly class ProfileFieldSectionViewModel
{
    /**
     * @param array<int, ProfileFieldViewModel> $fields
     */
    public function __construct(
        public string $title,
        public string $description,
        public array $fields,
    ) {}

    public function hasFields(): bool
    {
        return !empty($this->fields);
    }
}