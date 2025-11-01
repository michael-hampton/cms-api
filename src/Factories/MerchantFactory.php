<?php

namespace App\Factories;

use App\Framework\Support\Str;
use App\Framework\Tests\Factories\Factory;
use App\Framework\Tests\Factories\HasSiteId;
use App\Models\Author;
use App\Models\Merchant;
use App\Models\Order;

class MerchantFactory extends Factory
{
    protected function model(): string
    {
        return Merchant::class;
    }

    protected function definition(): array
    {
        return [
            'name' => 'Test Merchant ' . uniqid(),
            'slug' => Str::slug('Test Merchant ' . uniqid()),
        ];
    }

    public function named(string $name): static
    {
        return $this->state([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
        ]);
    }
}