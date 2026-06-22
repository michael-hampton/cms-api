<?php

namespace App\Models;

use App\Enums\Subscriptions\PrintFulfillmentStatus;

/**
 * @property int $id
 * @property int $batch_id
 * @property int $subscription_issue_fulfilment_id
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
        'subscription_issue_fulfilment_id',
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

    public function subscriptionIssueFulfilment(bool $relation = false)
    {
        return $this->hasOne(SubscriptionIssueFulfilment::class, 'id', 'subscription_issue_fulfilment_id', $relation);
    }

    public function batch(bool $relation = false)
    {
        return $this->hasOne(PrintBatch::class, 'id', 'batch_id', $relation);
    }

    public function subscription(bool $relation = false)
    {
        return $this->hasOne(Subscription::class, 'id', 'subscription_id', $relation);
    }
}