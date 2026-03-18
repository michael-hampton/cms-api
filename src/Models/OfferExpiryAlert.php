<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Alerts\AlertableEntityType;
use App\Enums\Alerts\ExpiryAlertThreshold;

/**
 * Tracks expiry alerts that have already been dispatched.
 *
 * @property int $id
 * @property AlertableEntityType $entity_type
 * @property int $entity_id
 * @property ExpiryAlertThreshold $threshold_hours
 * @property \DateTime $sent_at
 */
class OfferExpiryAlert extends Model
{
    protected $table = 'offer_expiry_alerts';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'threshold_hours',
        'sent_at',
    ];

    protected $casts = [
        'entity_type' => AlertableEntityType::class,
        'threshold_hours' => ExpiryAlertThreshold::class,
        'sent_at' => 'datetime',
    ];
}