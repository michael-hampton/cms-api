<?php

declare(strict_types=1);

namespace App\Resources;

use App\Framework\Resource\JsonResource;

/**
 * Report/listing representation of a PrintBatch.
 *
 * `file_exists` and `file_size_bytes` are intentionally NOT included here —
 * they require a transport round-trip (a network call for the SFTP
 * transport), which is too expensive to pay per-row on a paginated list.
 * PrintBatchReportController::show() augments a single record with those
 * fields directly, where the cost of one lookup is acceptable.
 */
class PrintBatchResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'issue_delivery_id' => $this->getAttribute('issue_delivery_id'),
            'territory_id' => $this->getAttribute('territory_id'),
            'status' => $this->getAttribute('status'),
            'format' => $this->getAttribute('format'),
            'filename' => $this->filename(),
            'file_path' => $this->getAttribute('file_path'),
            'export_attempt_count' => $this->getAttribute('export_attempt_count'),
            'exported_at' => $this->getAttribute('exported_at'),
            'estimated_delivery_date' => $this->whenLoaded(
                'issueDelivery',
                fn($issueDelivery) => $issueDelivery->estimated_delivery_date?->format('Y-m-d H:i:s'),
            ),
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
