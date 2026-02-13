<?php

namespace App\Services\Billing\Preorder;

use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Services\Billing\Preorder\Contracts\AvailabilityPolicyInterface;

class IssueAvailabilityPolicy implements AvailabilityPolicyInterface
{
    public function __construct(
        private readonly IssueDelivery            $issue,
        private readonly ?IssueDeliveryRepository $deliveryRepository = null
    )
    {
    }

    public function getAvailabilityMessage(): string
    {
        if ($this->isPreRelease()) {
            $date = $this->issue->on_sale_date->format('M j, Y');
            return "Issue #{$this->issue->issue_number} - Pre-order (On Sale: {$date})";
        }

        if ($this->issue->stock_quantity > 0) {
            return "Issue #{$this->issue->issue_number} - In Stock";
        }

        if ($this->isPreOrder()) {
            $date = $this->issue->restock_date->format('M j, Y');
            return "Issue #{$this->issue->issue_number} - Pre-order (Ships: {$date})";
        }

        return "Issue #{$this->issue->issue_number} - Out of Stock";
    }

    public function isPreRelease(): bool
    {
        // Issue hasn't been released yet but can be pre-ordered
        return $this->issue->on_sale_date
            && $this->issue->on_sale_date > now_datetime()
            && $this->canPurchase();
    }

    public function canPurchase(): bool
    {
        // In stock
        if ($this->issue->stock_quantity > 0) {
            return true;
        }

        // Pre-order available
        if ($this->issue->preorder_enabled && $this->issue->restock_date) {
            return true;
        }

        return false;
    }

    public function isPreOrder(): bool
    {
        $pendingPreorderQty = $this->getPendingPreorderQuantity();

        return ($this->issue->stock_quantity - $pendingPreorderQty) <= 0
            && $this->issue->preorder_enabled
            && $this->issue->restock_date !== null;
    }

    private function getPendingPreorderQuantity(): int
    {
        $repository = $this->deliveryRepository ?? app(IssueDeliveryRepository::class);
        return $repository->getPendingPreorderQuantity($this->issue->id);
    }

    public function getExpectedShipDate(): ?\DateTime
    {
        if ($this->isPreRelease()) {
            return $this->issue->on_sale_date;
        }

        if ($this->isPreOrder()) {
            return $this->issue->restock_date;
        }

        return null;
    }
}