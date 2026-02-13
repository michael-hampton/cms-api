<?php

namespace App\Services\Billing\Preorder\Contracts;

interface AvailabilityPolicyInterface
{
    public function canPurchase(): bool;

    public function isPreOrder(): bool;

    public function isPreRelease(): bool;

    public function getAvailabilityMessage(): string;

    public function getExpectedShipDate(): ?\DateTime;
}