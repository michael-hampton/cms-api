<?php

namespace App\ViewModels\OpenCollab;

use App\Framework\Support\Collection;

final readonly class ProfileStepViewModel
{
    /**
     * @param array<string, ProfileFieldViewModel> $fieldsByKey
     * @param array<int, ProfileFieldSectionViewModel> $additionalSections
     * @param array<int, array{key: string, required: bool, type: string, renderType: string}> $frontendFields
     */
    public function __construct(
        private array $fieldsByKey,
        public array  $additionalSections,
        public array  $frontendFields,
    ) {}

    public static function fromFields(Collection|array $fields, mixed $profile): self
    {
        $fieldObjects = $fields instanceof Collection
            ? $fields->all()
            : $fields;

        $mapped = [];

        foreach ($fieldObjects as $field) {
            $vm = ProfileFieldViewModel::fromDefinition($field, $profile);
            $mapped[$vm->key] = $vm;
        }

        $knownKeys = [
            'display_name',
            'bio',
            'avatar',
            'tax_country',
            'timezone',
            'expertise',
            'portfolio_url',
            'writing_samples',
            'linkedin_url',
            'twitter_url',
            'instagram_url',
            'tiktok_url',
        ];

        $customFields = array_filter(
            $mapped,
            static fn(ProfileFieldViewModel $field) => !in_array($field->key, $knownKeys, true),
        );

        // Ticket 2 fix: $additionalSections was hardcoded to [] — now populated correctly.
        $additionalSections = [];

        if (!empty($customFields)) {
            $additionalSections[] = new ProfileFieldSectionViewModel(
                title: 'Additional information',
                description: 'Extra profile information requested by this site.',
                fields: array_values($customFields),
            );
        }

        return new self(
            fieldsByKey: $mapped,
            additionalSections: $additionalSections,
            frontendFields: array_values(array_map(
                static fn(ProfileFieldViewModel $field) => [
                    'key'        => $field->key,
                    'required'   => $field->required,
                    'type'       => $field->type,
                    'renderType' => $field->renderType,
                ],
                $mapped,
            )),
        );
    }

    public function field(string $key): ?ProfileFieldViewModel
    {
        return $this->fieldsByKey[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->fieldsByKey[$key]);
    }

    public function photoField(): ?ProfileFieldViewModel
    {
        return $this->field('avatar');
    }

    public function bioField(): ?ProfileFieldViewModel
    {
        return $this->field('bio');
    }

    public function displayNameField(): ?ProfileFieldViewModel
    {
        return $this->field('display_name');
    }

    public function expertiseField(): ?ProfileFieldViewModel
    {
        return $this->field('expertise');
    }

    public function writingSamplesField(): ?ProfileFieldViewModel
    {
        return $this->field('writing_samples');
    }

    public function locationFields(): array
    {
        return array_values(array_filter([
            $this->field('tax_country'),
            $this->field('timezone'),
        ]));
    }

    public function socialFields(): array
    {
        return array_values(array_filter([
            $this->field('linkedin_url'),
            $this->field('twitter_url'),
            $this->field('instagram_url'),
            $this->field('tiktok_url'),
        ]));
    }

    public function portfolioField(): ?ProfileFieldViewModel
    {
        return $this->field('portfolio_url');
    }
}
