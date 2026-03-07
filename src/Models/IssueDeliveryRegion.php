<?php

namespace App\Models;

/**
 * Records which regions an issue delivery supports.
 * When at least one row exists for an issue_delivery_id, the delivery is
 * treated as regional and one export batch is generated per region.
 *
 * @property int $issue_delivery_id
 * @property int $region_id
 */
class IssueDeliveryRegion extends Model
{
    public $timestamps = false;
    protected $table = 'issue_delivery_regions';
    protected $fillable = [
        'issue_delivery_id',
        'region_id',
    ];
}