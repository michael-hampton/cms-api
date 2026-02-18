<?php

namespace App\Factories;

use App\Enums\Vouchers\VoucherType;
use App\Framework\Tests\Factories\Factory;
use App\Framework\Tests\Factories\HasSiteId;
use App\Models\Voucher;

class VoucherFactory extends Factory
{
    use HasSiteId;

    protected function model(): string
    {
        return Voucher::class;
    }

    protected function definition(): array
    {
        return $this->withSiteId([
            'value' => 99.99,
            'code' => 'TEST-' . uniqid(),
            'name' => 'Test Voucher',
            'usage_count' => 0,
            'description' => 'Test description',
            'minimum_order_value' => 100,
            'type' => 'fixed',
            'is_active' => true,
        ]);
    }

    public function percentage(float $discount): static
    {
        return $this->state([
            'type' => VoucherType::Percentage->value,
            'discount' => $discount,
        ]);
    }

    public function fixed(float $value): static
    {
        return $this->state([
            'type' => 'fixed',
            'value' => $value,
        ]);
    }

    public function withCode(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}