<?php

namespace App\DTO\Briefs;

use InvalidArgumentException;

/**
 * Typed value object for a subtask definition stored in the
 * BriefTemplate::default_subtasks JSON column.
 *
 * No separate database table — embedded in BriefTemplate.
 */
class BriefPresetSubtask
{
    public function __construct(
        public readonly string  $title,
        public readonly ?string $description = null,
        public readonly ?string $defaultOwnerId = null,
        public readonly ?string $defaultReviewerId = null,
    )
    {
    }

    /**
     * @throws InvalidArgumentException if title is blank
     */
    public static function fromArray(array $data): self
    {
        $title = trim($data['title'] ?? '');

        if ($title === '') {
            throw new InvalidArgumentException(
                'BriefPresetSubtask requires a non-blank title.'
            );
        }

        return new self(
            title: $title,
            description: isset($data['description']) ? (string)$data['description'] : null,
            defaultOwnerId: isset($data['defaultOwnerId']) ? (string)$data['defaultOwnerId'] : null,
            defaultReviewerId: isset($data['defaultReviewerId']) ? (string)$data['defaultReviewerId'] : null,
        );
    }

    /**
     * Only non-null values are included so the stored JSON stays compact.
     */
    public function toArray(): array
    {
        $result = ['title' => $this->title];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->defaultOwnerId !== null) {
            $result['defaultOwnerId'] = $this->defaultOwnerId;
        }

        if ($this->defaultReviewerId !== null) {
            $result['defaultReviewerId'] = $this->defaultReviewerId;
        }

        return $result;
    }
}