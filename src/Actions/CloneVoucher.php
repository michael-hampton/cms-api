<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Models\Voucher;
use App\Repositories\VoucherRepository;

class CloneVoucher
{
    public function __construct(private readonly Database $database, private readonly VoucherRepository $repository)
    {
    }

    public function handle(int $voucherId, ?string $newCode = null): ?Voucher
    {
        return $this->database->transaction(function() use ($voucherId, $newCode) {
            $originalVoucher = $this->repository->find($voucherId);

            if (!$originalVoucher) {
                throw new \Exception("Voucher not found");
            }

            $data = [
                'name' => $originalVoucher->name . ' (Copy)',
                'description' => $originalVoucher->description,
                'type' => $originalVoucher->type,
                'value' => $originalVoucher->value,
                'minimum_order_value' => $originalVoucher->minimum_order_value,
                'maximum_discount' => $originalVoucher->maximum_discount,
                'usage_limit' => $originalVoucher->usage_limit,
                'usage_count' => 0,
                'per_user_limit' => $originalVoucher->per_user_limit,
                'starts_at' => $originalVoucher->starts_at?->format('Y-m-d H:i:s'),
                'expires_at' => $originalVoucher->expires_at?->format('Y-m-d H:i:s'),
                'status' => 'inactive',
                'site_id' => $originalVoucher->site_id,
            ];

            // Generate unique code
            if ($newCode) {
                $code = strtoupper(trim($newCode));
            } else {
                $baseCode = $originalVoucher->code;
                $code = $baseCode;
                $counter = 1;

                while ($this->repository->findByCode($code)) {
                    $code = $baseCode . $counter;
                    $counter++;
                }
            }

            $data['code'] = $code;

            $newVoucher = $this->repository->create($data);

            // Duplicate product associations
            $productIds = $originalVoucher->products()?->pluck('id')->toArray();
            if (!empty($productIds)) {
                $newVoucher->products(true)->sync($productIds);
            }

            // Duplicate category associations
            $categoryIds = $originalVoucher->categories()?->pluck('id')->toArray();
            if (!empty($categoryIds)) {
                $newVoucher->categories(true)->sync($categoryIds);
            }

            // Duplicate brand associations
            $brandIds = $originalVoucher->brands()?->pluck('id')->toArray();
            if (!empty($brandIds)) {
                $newVoucher->brands(true)->sync($brandIds);
            }

            // Add clone history
            $originalVoucher->addCloneRecord('cloned_to', $newVoucher->id, null);
            $newVoucher->addCloneRecord('cloned_from', $originalVoucher->id, null);

            return $newVoucher;
        });
    }
}