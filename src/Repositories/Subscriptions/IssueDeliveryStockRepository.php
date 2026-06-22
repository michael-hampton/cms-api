<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions;

use App\Framework\Database\Database;
use App\Models\IssueDelivery;

class IssueDeliveryStockRepository
{
    public function getAvailableStock(int $issueDeliveryId): int
    {
        $issue = IssueDelivery::find($issueDeliveryId);

        if (!$issue) {
            return 0;
        }

        return max(0, (int) $issue->stock_quantity);
    }

    public function hasAvailableStock(int $issueDeliveryId): bool
    {
        return $this->getAvailableStock($issueDeliveryId) > 0;
    }

    public function decrementIfAvailable(int $issueDeliveryId): bool
    {
        $affectedRows = Database::getInstance()->exec(
            'UPDATE issue_deliveries SET stock_quantity = stock_quantity - 1 WHERE id = ? AND stock_quantity > 0',
            [$issueDeliveryId]
        );

        return (int) $affectedRows === 1;
    }
}
