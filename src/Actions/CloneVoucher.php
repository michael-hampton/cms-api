<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Repositories\VoucherRepository;

class CloneVoucher
{
    public function __construct(private readonly Database $database, private readonly VoucherRepository $repository)
    {
    }

    public function handle(int $voucherId, ?string $newCode = null): array
    {
        return $this->database->transaction(function() use ($voucherId, $newCode) {
            $results = [
                'success' => [],
                'failed' => [],
                'products_associated' => 0,
                'categories_associated' => 0,
                'brands_associated' => 0
            ];

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

            try {
                $newVoucher = $this->repository->create($data);

                $results['success'][] = 'voucher_created';
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'operation' => 'create_voucher',
                    'error' => $e->getMessage()
                ];
                throw $e;
            }

            // Duplicate product associations
            try {
                $productIds = $originalVoucher->products()?->pluck('id')->toArray();
                if (!empty($productIds)) {
                    $newVoucher->products(true)->sync($productIds);
                    $results['products_associated'] = count($productIds);
                    $results['success'][] = 'products_associated';
                }
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'operation' => 'associate_products',
                    'error' => $e->getMessage()
                ];
            }

            // Duplicate category associations
            try {
                $categoryIds = $originalVoucher->categories()?->pluck('id')->toArray();
                if (!empty($categoryIds)) {
                    $newVoucher->categories(true)->sync($categoryIds);
                    $results['categories_associated'] = count($categoryIds);
                    $results['success'][] = 'categories_associated';
                }
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'operation' => 'associate_categories',
                    'error' => $e->getMessage()
                ];
            }

            // Duplicate brand associations
            try {
                $brandIds = $originalVoucher->brands()?->pluck('id')->toArray();
                if (!empty($brandIds)) {
                    $newVoucher->brands(true)->sync($brandIds);
                    $results['brands_associated'] = count($brandIds);
                    $results['success'][] = 'brands_associated';
                }
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'operation' => 'associate_brands',
                    'error' => $e->getMessage()
                ];
            }

            // Add clone history
            try {
                $originalVoucher->addCloneRecord('cloned_to', $newVoucher->id, null);
                $newVoucher->addCloneRecord('cloned_from', $originalVoucher->id, null);
                $results['success'][] = 'clone_history';
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'operation' => 'clone_history',
                    'error' => $e->getMessage()
                ];
            }

            return [
                'voucher' => $newVoucher,
                'results' => $results,
                'original_voucher_id' => $voucherId
            ];
        });
    }
}