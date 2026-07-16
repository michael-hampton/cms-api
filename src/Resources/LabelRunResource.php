<?php

declare(strict_types=1);

namespace App\Resources;

use App\Framework\Resource\JsonResource;

/**
 * Report/listing representation of a LabelRun.
 *
 * `file_exists` and `file_size_bytes` are intentionally NOT included here —
 * LabelRunReportController::show() augments a single record with those
 * fields directly; computing them per-row on a paginated list of what can
 * be thousands of labels per issue is too expensive to do unconditionally.
 */
class LabelRunResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'subscription_issue_fulfilment_id' => $this->getAttribute('subscription_issue_fulfilment_id'),
            'print_batch_id' => $this->getAttribute('print_batch_id'),
            'subscription_id' => $this->getAttribute('subscription_id'),
            'status' => $this->getAttribute('status'),
            'format' => $this->getAttribute('format'),
            'filename' => $this->filename(),
            'file_path' => $this->getAttribute('file_path'),
            'transport' => $this->getAttribute('transport'),
            'attempt_count' => $this->getAttribute('attempt_count'),
            'generated_at' => $this->getAttribute('generated_at')?->format('Y-m-d H:i:s'),
            'failure_reason' => $this->getAttribute('failure_reason'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),
        ];
    }

    private function filename(): ?string
    {
        $path = $this->getAttribute('file_path');

        return $path ? basename($path) : null;
    }
}
