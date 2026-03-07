<?php

namespace App\Models;

use App\Enums\Subscriptions\PrintFulfillmentStatus;

/**
 * @property int $id
 * @property int $batch_id
 * @property int $issues_delivered_id
 * @property int $subscription_id
 * @property string $full_name
 * @property array $delivery_address_snapshot
 * @property string $address_line_1
 * @property string|null $address_line_2
 * @property string $city
 * @property string $postcode
 * @property string $country
 * @property string|null $tracking_number
 * @property string $status
 */
class PrintFulfillment extends Model
{
    protected $table = 'print_fulfillments';

    protected $fillable = [
        'batch_id',
        'issues_delivered_id',
        'subscription_id',
        'full_name',
        'delivery_address_snapshot',
        'address_line_1',
        'address_line_2',
        'city',
        'postcode',
        'country',
        'tracking_number',
        'status',
        'territory_id'
    ];

    protected $casts = [
        'delivery_address_snapshot' => 'array',
    ];

    public function updateTrackingNumber(string $trackingNumber): void
    {
        $this->update([
            'tracking_number' => $trackingNumber,
            'status' => PrintFulfillmentStatus::SHIPPED->value,
        ]);
    }

    public function issuesDelivered(bool $relation = false)
    {
        return $this->hasOne(IssuesDelivered::class, 'id', 'issues_delivered_id', $relation);
    }
}