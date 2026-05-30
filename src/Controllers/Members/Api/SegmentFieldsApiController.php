<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Enums\Member\SegmentSubjectType;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Services\MemberInsights\Segmentation\SegmentFieldRegistry;

/**
 * GET /api/admin/segment-fields?subject_type=member|subscription|plan
 *
 * Returns the filterable field definitions for the segment rule builder,
 * sourced from SegmentFieldRegistry — the single source of truth for fields,
 * types, and valid operators.
 *
 * The registry is also used by the rule engine for server-side validation,
 * so the UI and the evaluator are always in sync.
 */
class SegmentFieldsApiController extends Controller
{
    public function __construct(
        private readonly SegmentFieldRegistry $registry,
    ) {
        parent::__construct();
    }

    public function __invoke(Request $request): JsonResponse
    {
        $raw = $request->get('subject_type', SegmentSubjectType::Member->value);

        $subjectType = SegmentSubjectType::tryFrom($raw);

        if ($subjectType === null) {
            return $this->errorResponse(
                "Invalid subject_type \"{$raw}\". Allowed values: "
                . implode(', ', array_column(SegmentSubjectType::cases(), 'value')),
                422,
            );
        }

        $fields = $this->registry->getFields($subjectType);

        // Transform from the registry's keyed-by-path format into a flat array
        // suitable for the frontend field picker:
        //
        //   { value, label, group, type, operators }
        //
        // `group` is derived from the dot-notation prefix so the UI can render
        // optgroups without any additional config. Single-segment keys (e.g.
        // "flags") fall into a "General" group.
        $response = [];

        foreach ($fields as $path => $definition) {
            $prefix = str_contains($path, '.') ? explode('.', $path)[0] : 'general';

            $response[] = [
                'value'     => $path,
                'label'     => $definition['label'],
                'group'     => $this->groupLabel($prefix),
                'type'      => $definition['type'],
                'operators' => $definition['operators'],
            ];
        }

        return $this->resourceResponse(['fields' => $response]);
    }

    /**
     * Convert a dot-notation prefix into a human-readable group label.
     * e.g. "scores" → "Scores", "subscription" → "Subscription"
     */
    private function groupLabel(string $prefix): string
    {
        return ucfirst(str_replace('_', ' ', $prefix));
    }
}