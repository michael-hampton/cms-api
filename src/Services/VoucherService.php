<?php

namespace App\Services;

use App\Exceptions\CannotDeleteException;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Framework\Support\Str;
use App\Models\Voucher;
use App\Repositories\VoucherRepository;

class VoucherService
{
    private Database $database;
    protected VoucherRepository $repository;

    public function __construct(Database $database, VoucherRepository $repository)
    {
        $this->database = $database ?? Database::getInstance();
        $this->repository = $repository;
    }

    public function create(array $data): Voucher
    {
        return $this->repository->create($data);
    }

    public function update(int $voucherId, array $data): ?Voucher
    {
        return $this->repository->update($voucherId, $data);
    }

    public function delete(int $voucherId): bool
    {
        $voucher = $this->repository->find($voucherId);

        if (!$voucher) {
            throw new \Exception('Voucher not found');
        }

        if ($voucher->usage_count > 0) {
            throw new CannotDeleteException('voucher', $voucher->usage_count);
        }

        return $this->repository->delete($voucherId);
    }

    public function checkDeletable(int $voucherId): array
    {
        return $this->repository->checkDeletable($voucherId);
    }

    public function getAlternativeVouchers(int $voucherId): Collection
    {
        return $this->repository->getAlternatives($voucherId);
    }

    public function duplicateVoucher(int $voucherId, ?string $newCode = null): ?Voucher
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

            return $this->repository->create($data);
        });
    }

    public function validateVoucher(string $code, float $orderValue, ?int $userId = null): array
    {
        $voucher = $this->repository->findByCode($code);

        if (!$voucher) {
            return [
                'valid' => false,
                'message' => 'Voucher not found',
                'discount' => 0
            ];
        }

        if (!$voucher->isValid()) {
            $message = 'Voucher is not valid';

            if ($voucher->status === 'expired') {
                $message = 'Voucher has expired';
            } elseif ($voucher->status === 'inactive') {
                $message = 'Voucher is inactive';
            } elseif ($voucher->usage_limit && $voucher->usage_count >= $voucher->usage_limit) {
                $message = 'Voucher usage limit reached';
            }

            return [
                'valid' => false,
                'message' => $message,
                'discount' => 0
            ];
        }

        if ($voucher->minimum_order_value && $orderValue < $voucher->minimum_order_value) {
            return [
                'valid' => false,
                'message' => "Minimum order value of {$voucher->minimum_order_value} required",
                'discount' => 0
            ];
        }

        $discount = $voucher->calculateDiscount($orderValue);

        return [
            'valid' => true,
            'message' => 'Voucher applied successfully',
            'discount' => $discount,
            'voucher_id' => $voucher->id,
            'voucher' => $voucher
        ];
    }

    public function applyVoucher(int $voucherId): bool
    {
        return $this->repository->incrementUsageCount($voucherId);
    }

    public function updateExpiredVouchers(): int
    {
        return $this->repository->updateExpiredVouchers();
    }
}