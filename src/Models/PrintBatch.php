<?php

namespace App\Models;

use App\Enums\Subscriptions\PrintBatchStatus;

/**
 * @property int $id
 * @property int $issue_delivery_id
 * @property string $status
 * @property string $format
 * @property int $export_attempt_count
 * @property string|null $file_path
 * @property string|null $exported_at
 */
class PrintBatch extends Model
{
    protected $table = 'print_batches';

    protected $fillable = [
        'issue_delivery_id',
        'status',
        'format',
        'export_attempt_count',
        'file_path',
        'exported_at',
    ];

    public function markExporting(): void
    {
        $this->update([
            'status' => PrintBatchStatus::BATCH_EXPORTING->value,
            'export_attempt_count' => $this->export_attempt_count + 1,
        ]);

        // Keep the in-memory value consistent so buildFilename() in the export
        // service reads the incremented count without a reload.
        $this->export_attempt_count += 1;
    }

    public function markExported(string $filePath): void
    {
        $this->update([
            'status' => PrintBatchStatus::BATCH_EXPORTED->value,
            'file_path' => $filePath,
            'exported_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }

    public function markFailed(): void
    {
        $this->update(['status' => PrintBatchStatus::BATCH_FAILED->value]);
    }

    public function isExported(): bool
    {
        return $this->status === PrintBatchStatus::BATCH_EXPORTED->value;
    }

    public function isExporting(): bool
    {
        return $this->status === PrintBatchStatus::BATCH_EXPORTING->value;
    }
}