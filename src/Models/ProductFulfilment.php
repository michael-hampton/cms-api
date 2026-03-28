<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Products\ProductFulfilmentStatus;

/**
 * Represents a single physical-product fulfilment record.
 *
 * Parallel to PrintFulfillment in the print pipeline.
 * One record per order line per batch cycle.
 *
 * @property int $id
 * @property int $product_batch_id
 * @property int $order_id
 * @property int $order_line_id
 * @property string $sku
 * @property int $quantity
 * @property string $full_name
 * @property array $delivery_address_snapshot
 * @property string $address_line_1
 * @property string|null $address_line_2
 * @property string $city
 * @property string $postcode
 * @property string $country
 * @property string|null $tracking_number
 * @property int|null $territory_id
 * @property string $status
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 */
class ProductFulfilment extends Model
{
    protected $table = 'product_fulfilments';

    protected $fillable = [
        'product_batch_id',
        'order_id',
        'order_line_id',
        'sku',
        'quantity',
        'full_name',
        'delivery_address_snapshot',
        'address_line_1',
        'address_line_2',
        'city',
        'postcode',
        'country',
        'tracking_number',
        'territory_id',
        'status',
    ];

    protected $casts = [
        'delivery_address_snapshot' => 'array',
        'quantity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function updateTrackingNumber(string $trackingNumber): void
    {
        $this->update([
            'tracking_number' => $trackingNumber,
            'status' => ProductFulfilmentStatus::SHIPPED->value,
        ]);
    }

    public function isExported(): bool
    {
        return $this->status === ProductFulfilmentStatus::EXPORTED->value;
    }
}