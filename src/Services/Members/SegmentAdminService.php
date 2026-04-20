<?php

namespace App\Services\Members;

use App\Enums\Member\SegmentRuleBoolean;
use App\Enums\Member\SegmentRuleOperator;
use App\Framework\Database\Database;
use App\Models\Segment;
use App\Repositories\MemberInsights\SegmentRepository;
use App\Repositories\MemberInsights\SegmentRuleRepository;

class SegmentAdminService
{
    public function __construct(
        private readonly SegmentRepository     $segmentRepository,
        private readonly SegmentRuleRepository $segmentRuleRepository,
        private readonly Database              $database,
    )
    {
    }

    public function list(
        int     $page = 1,
        int     $perPage = 20,
        ?string $search = null,
        string  $sortBy = 'name',
        string  $sortOrder = 'asc',
    ): array
    {
        return $this->segmentRepository->paginateAdmin($perPage, $page, $search, $sortBy, $sortOrder);
    }

    public function create(array $payload): Segment
    {
        $this->validateRules($payload['rules'] ?? []);

        if ($this->segmentRepository->existsByKey($payload['key'])) {
            throw new \InvalidArgumentException("Segment key \"{$payload['key']}\" already exists.");
        }

        return $this->database->transaction(function () use ($payload) {
            $segment = $this->segmentRepository->create([
                'key' => trim($payload['key']),
                'name' => trim($payload['name']),
                'description' => $payload['description'] ?? null,
                'category' => $payload['category'] ?? null,
                'is_active' => $payload['is_active'] ?? true,
            ]);

            $this->segmentRuleRepository->createManyForSegment($segment->id, $payload['rules'] ?? []);

            return $this->find($segment->id);
        });
    }

    private function validateRules(array $rules): void
    {
        foreach ($rules as $index => $rule) {
            $position = $index + 1;

            if (empty($rule['field'])) {
                throw new \InvalidArgumentException("Rule #{$position}: field is required.");
            }

            if (empty($rule['operator']) || SegmentRuleOperator::tryFrom($rule['operator']) === null) {
                throw new \InvalidArgumentException("Rule #{$position}: operator is invalid.");
            }

            if (!array_key_exists('value', $rule)) {
                throw new \InvalidArgumentException("Rule #{$position}: value is required.");
            }

            if (
                !empty($rule['boolean']) &&
                SegmentRuleBoolean::tryFrom($rule['boolean']) === null
            ) {
                throw new \InvalidArgumentException("Rule #{$position}: boolean is invalid.");
            }
        }
    }

    public function find(int $id): Segment
    {
        $segment = $this->segmentRepository->findWithRules($id);

        if ($segment === null) {
            throw new \InvalidArgumentException("Segment #{$id} not found.");
        }

        return $segment;
    }

    public function update(int $id, array $payload): Segment
    {
        $segment = $this->find($id);

        if (
            isset($payload['key']) &&
            $this->segmentRepository->existsByKey($payload['key'], $segment->id)
        ) {
            throw new \InvalidArgumentException("Segment key \"{$payload['key']}\" already exists.");
        }

        if (array_key_exists('rules', $payload)) {
            $this->validateRules($payload['rules'] ?? []);
        }

        return $this->database->transaction(function () use ($segment, $payload) {
            $this->segmentRepository->update($segment->id, array_filter([
                'key' => isset($payload['key']) ? trim($payload['key']) : null,
                'name' => isset($payload['name']) ? trim($payload['name']) : null,
                'description' => $payload['description'] ?? null,
                'category' => $payload['category'] ?? null,
                'is_active' => $payload['is_active'] ?? null,
            ], fn($value) => $value !== null));

            if (array_key_exists('rules', $payload)) {
                $this->segmentRuleRepository->deleteBySegmentId($segment->id);
                $this->segmentRuleRepository->createManyForSegment($segment->id, $payload['rules'] ?? []);
            }

            return $this->find($segment->id);
        });
    }

    public function delete(int $id): void
    {
        $segment = $this->find($id);

        $this->database->transaction(function () use ($segment) {
            $this->segmentRepository->delete($segment->id);
        });
    }
}
