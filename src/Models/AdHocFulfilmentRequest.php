<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Subscriptions\AdHocFulfilmentProcess;

/**
 * Audit record of an admin manually requesting generation of a fulfilment
 * file, outside the normal scheduled workflow.
 *
 * This row is created once per request, at the moment the admin triggers
 * generation — it does not track generation progress itself. Progress and
 * the resulting file live on the referenced process record (currently only
 * PrintBatch), which is the same record the scheduled pipeline writes to.
 *
 * @property int $id
 * @property string $process             AdHocFulfilmentProcess value
 * @property int|null $print_batch_id
 * @property int $requested_by_user_id
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 */
class AdHocFulfilmentRequest extends Model
{
    protected $table = 'ad_hoc_fulfilment_requests';

    protected $fillable = [
        'process',
        'print_batch_id',
        'requested_by_user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function printBatch(bool $relation = false)
    {
        return $this->belongsTo(PrintBatch::class, 'print_batch_id', 'id', $relation);
    }

    public function requestedBy(bool $relation = false)
    {
        return $this->belongsTo(User::class, 'requested_by_user_id', 'id', $relation);
    }

    // =========================================================================
    // Queries
    // =========================================================================

    public function process(): AdHocFulfilmentProcess
    {
        return AdHocFulfilmentProcess::from($this->process);
    }
}