<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Repositories\Cms\VoucherRepository;

class BulkDeleteVoucher
{
    public function __construct(private readonly Database $database, private readonly VoucherRepository $repository)
    {
    }
    public function handle(array $voucherIds): array
    {
        return $this->database->transaction(function() use ($voucherIds) {
            $deleted = [];
            $failed = [];

            foreach ($voucherIds as $voucherId) {
                try {
                    $voucher = $this->repository->find($voucherId);

                    if (!$voucher) {
                        $failed[] = ['id' => $voucherId, 'reason' => 'Voucher not found'];
                        continue;
                    }

                    if ($voucher->usage_count > 0) {
                        $failed[] = [
                            'id' => $voucherId,
                            'reason' => "Voucher has been used {$voucher->usage_count} times"
                        ];
                        continue;
                    }

                    if ($this->repository->delete($voucherId)) {
                        $deleted[] = $voucherId;
                    } else {
                        $failed[] = ['id' => $voucherId, 'reason' => 'Delete failed'];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['id' => $voucherId, 'reason' => $e->getMessage()];
                }
            }

            return [
                'deleted' => $deleted,
                'failed' => $failed,
                'total' => count($voucherIds)
            ];
        });
    }
}