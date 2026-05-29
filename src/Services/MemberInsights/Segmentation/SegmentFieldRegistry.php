<?php

namespace App\Services\MemberInsights\Segmentation;

use App\Enums\Member\SegmentSubjectType;

/**
 * Code-defined registry of all supported segment fields, their types, and
 * the operators that are valid for each field type.
 *
 * This is the single source of truth for:
 *   - The segment builder UI (field dropdown, operator options)
 *   - Server-side rule validation (Ticket 5 rule engine)
 *
 * Adding a new field: add an entry to the appropriate subject_type block.
 * Field keys use dot-notation mirroring the profile/subscription data shape.
 */
class SegmentFieldRegistry
{
    /**
     * @return array<string, array{type: string, label: string, operators: string[]}>
     *         Keyed by field path, filtered to the requested subject type.
     */
    public function getFields(SegmentSubjectType $subjectType): array
    {
        return $this->allFields()[$subjectType->value] ?? [];
    }

    /**
     * Returns true if the given field path is registered for the subject type.
     */
    public function isValidField(SegmentSubjectType $subjectType, string $field): bool
    {
        return isset($this->getFields($subjectType)[$field]);
    }

    /**
     * Returns true if the operator is valid for the given field on the subject type.
     *
     * @throws \InvalidArgumentException if the field is unknown.
     */
    public function isValidOperator(SegmentSubjectType $subjectType, string $field, string $operator): bool
    {
        $fields = $this->getFields($subjectType);

        if (!isset($fields[$field])) {
            throw new \InvalidArgumentException(
                "Field \"{$field}\" is not registered for subject type \"{$subjectType->value}\"."
            );
        }

        return in_array($operator, $fields[$field]['operators'], true);
    }

    // -------------------------------------------------------------------------
    // Registry definition
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array<string, array{type: string, label: string, operators: string[]}>>
     */
    private function allFields(): array
    {
        return [
            SegmentSubjectType::Member->value => [
                'scores.activity_score' => [
                    'type'      => 'number',
                    'label'     => 'Activity Score',
                    'operators' => ['=', '!=', '>', '>=', '<', '<='],
                ],
                'trends.7d_change' => [
                    'type'      => 'number',
                    'label'     => '7-Day Trend Change',
                    'operators' => ['=', '!=', '>', '>=', '<', '<='],
                ],
                'behaviour.profile_type' => [
                    'type'      => 'enum',
                    'label'     => 'Profile Type',
                    'operators' => ['=', '!=', 'IN', 'NOT_IN'],
                ],
                'summary.total_actions' => [
                    'type'      => 'number',
                    'label'     => 'Total Actions',
                    'operators' => ['=', '!=', '>', '>=', '<', '<='],
                ],
                'flags' => [
                    'type'      => 'array',
                    'label'     => 'Flags',
                    'operators' => ['CONTAINS', 'NOT_CONTAINS'],
                ],
            ],

            SegmentSubjectType::Subscription->value => [
                'subscription.renewal_date' => [
                    'type'      => 'date',
                    'label'     => 'Renewal Date',
                    'operators' => ['before', 'after', 'between', 'within_next_days'],
                ],
                'subscription.payment_type' => [
                    'type'      => 'enum',
                    'label'     => 'Payment Type',
                    'operators' => ['=', '!=', 'IN', 'NOT_IN'],
                ],
                'subscription.status' => [
                    'type'      => 'enum',
                    'label'     => 'Status',
                    'operators' => ['=', '!=', 'IN', 'NOT_IN'],
                ],
                'subscription.price' => [
                    'type'      => 'number',
                    'label'     => 'Price',
                    'operators' => ['=', '!=', '>', '>=', '<', '<='],
                ],
                'subscription.start_date' => [
                    'type'      => 'date',
                    'label'     => 'Start Date',
                    'operators' => ['before', 'after', 'between'],
                ],
            ],

            SegmentSubjectType::Plan->value => [
                'plan.product_type' => [
                    'type'      => 'enum',
                    'label'     => 'Product Type',
                    'operators' => ['=', '!=', 'IN', 'NOT_IN'],
                ],
                'plan.billing_period' => [
                    'type'      => 'enum',
                    'label'     => 'Billing Period',
                    'operators' => ['=', '!='],
                ],
                'plan.price' => [
                    'type'      => 'number',
                    'label'     => 'Price',
                    'operators' => ['=', '!=', '>', '>=', '<', '<='],
                ],
                'plan.region' => [
                    'type'      => 'enum',
                    'label'     => 'Region',
                    'operators' => ['=', '!=', 'IN', 'NOT_IN'],
                ],
            ],
        ];
    }
}