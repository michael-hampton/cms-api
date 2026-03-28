<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Products\ProductBatchStatus;

/**
 * Groups ProductFulfilment records by territory for a single export cycle.
 *
 * Parallel to PrintBatch in the print pipeline.
 * One ProductBatch per territory per fulfilment run.
 *
 * @property int $id
 * @property int $fulfilment_run_id
 * @property int|null $territory_id
 * @property string $status
 * @property string $format
 * @property int $export_attempt_count
 * @property string|null $file_path
 * @property string|null $exported_at
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 */
class ProductBatch extends Model
{
    protected $table = 'product_batches';

    protected $fillable = [
        'fulfilment_run_id',
        'territory_id',
        'status',
        'format',
        'export_attempt_count',
        'file_path',
        'exported_at',
    ];

    protected $casts = [
        'export_attempt_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function markExporting(): void
    {
        $this->update([
            'status' => ProductBatchStatus::EXPORTING->value,
            'export_attempt_count' => $this->export_attempt_count + 1,
        ]);

        $this->export_attempt_count += 1;
    }

    public function markExported(string $filePath): void
    {
        $this->update([
            'status' => ProductBatchStatus::EXPORTED->value,
            'file_path' => $filePath,
            'exported_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }

    public function markFailed(): void
    {
        $this->update(['status' => ProductBatchStatus::FAILED->value]);
    }

    public function isExported(): bool
    {
        return $this->status === ProductBatchStatus::EXPORTED->value;
    }

    public function isExporting(): bool
    {
        return $this->status === ProductBatchStatus::EXPORTING->value;
    }
}