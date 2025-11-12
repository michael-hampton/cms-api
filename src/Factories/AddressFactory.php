<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Framework\Tests\Factories\HasSiteId;
use App\Models\Address;
use App\Models\Author;
use App\Models\Member;

class AddressFactory extends Factory
{
    use HasSiteId;

    protected function model(): string
    {
        return Address::class;
    }

    protected function definition(): array
    {
        return $this->withSiteId([
            'member_id' => uniqid(),
            'type' => 'shipping',
            'address_line_1' => '123 Main St',
            'city' => 'City',
            'postcode' => '12345',
            'country' => 'US',
        ]);
    }
}