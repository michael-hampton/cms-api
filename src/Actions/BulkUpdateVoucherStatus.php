<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Repositories\Cms\VoucherRepository;

class BulkUpdateVoucherStatus
{
    public function __construct(private readonly Database $database, private readonly VoucherRepository $repository)
    {
    }

    public function handle(array $voucherIds, string $status): array
    {
        return $this->database->transaction(function() use ($voucherIds, $status) {
            $updated = [];
            $failed = [];

            foreach ($voucherIds as $voucherId) {
                try {
                    $voucher = $this->repository->find($voucherId);

                    if (!$voucher) {
                        $failed[] = ['id' => $voucherId, 'reason' => 'Voucher not found'];
                        continue;
                    }

                    $updatedVoucher = $this->repository->update($voucherId, ['status' => $status]);

                    if ($updatedVoucher) {
                        $updated[] = $voucherId;
                    } else {
                        $failed[] = ['id' => $voucherId, 'reason' => 'Update failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $voucherId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'updated' => $updated,
                'failed' => $failed,
                'total' => count($voucherIds)
            ];
        });
    }
}