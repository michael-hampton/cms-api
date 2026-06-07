<?php

namespace App\Models;

use App\Enums\OpenCollab\PayoutBatchStatus;

class PayoutBatch extends Model
{
    protected $table = 'oc_payout_batches';

    protected $fillable = [
        'site_id',
        'accrual_window_id',
        'status',
        'created_by',
        'started_at',
        'completed_at',
        'failed_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'accrual_window_id' => 'integer',
        'created_by' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function isDraft(): bool
    {
        return $this->status === PayoutBatchStatus::Draft->value;
    }

    public function isProcessing(): bool
    {
        return $this->status === PayoutBatchStatus::Processing->value;
    }

    public function isCompleted(): bool
    {
        return $this->status === PayoutBatchStatus::Completed->value;
    }

    public function isFailed(): bool
    {
        return $this->status === PayoutBatchStatus::Failed->value;
    }
}