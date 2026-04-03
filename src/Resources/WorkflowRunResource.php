<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

/**
 * Serialises a WorkflowRun model for API responses.
 *
 * The `summary` column is a keyed map of phase entries:
 *
 *   {
 *     "phase_2": {
 *       "status": "succeeded",
 *       "summary": { "batch_count": 1 },
 *       "recorded_at": "2026-03-31 11:39:54"
 *     }
 *   }
 *
 * The `input` column carries the trigger context:
 *
 *   {
 *     "triggered_by": "IssueDeliveryDispatchedListener",
 *     "issue_delivery_id": 408
 *   }
 *
 * Both are passed through as-is; the frontend owns presentation.
 */
class WorkflowRunResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'workflow_type' => $this->getAttribute('workflow_type'),
            'status' => $this->getAttribute('status'),
            'input' => $this->getAttribute('input') ?? [],
            'summary' => $this->formatSummary(),
            'error' => $this->getAttribute('error'),
            'started_at' => $this->formatDate($this->getAttribute('started_at')),
            'completed_at' => $this->formatDate($this->getAttribute('completed_at')),
            'created_at' => $this->formatDate($this->getAttribute('created_at')),
            'updated_at' => $this->formatDate($this->getAttribute('updated_at')),
        ];
    }

    // =========================================================================
    // Private
    // =========================================================================

    /**
     * Normalise the summary map so every phase entry has a consistent shape,
     * even if some fields were missing when the record was written.
     *
     * Returns null when no summary has been recorded yet (e.g. status=running).
     */
    private function formatSummary(): ?array
    {
        $raw = $this->getAttribute('summary');

        if (empty($raw) || !is_array($raw)) {
            return null;
        }

        $formatted = [];

        foreach ($raw as $phaseKey => $entry) {
            $formatted[$phaseKey] = [
                'status' => $entry['status'] ?? 'unknown',
                'summary' => $entry['summary'] ?? [],
                'recorded_at' => $entry['recorded_at'] ?? null,
            ];
        }

        return $formatted;
    }

    private function formatDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return $value->format('Y-m-d H:i:s');
    }
}