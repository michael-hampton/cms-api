<?php

namespace App\Models;

/**
 * Represents a single agent-initiated print issue replacement request.
 *
 * @property int $id
 * @property int $subscription_id
 * @property int $issue_id
 * @property string $reason
 * @property int $created_by
 * @property string $status           pending|queued|dispatched|failed
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 */
class FulfilmentReplacement extends Model
{
    protected $table = 'fulfilment_replacements';

    protected $fillable = [
        'subscription_id',
        'issue_delivery_id',
        'reason',
        'created_by',
        'status',
    ];

    protected $casts = [
        'subscription_id' => 'int',
        'issue_delivery_id' => 'int',
        'created_by' => 'int',
    ];
}