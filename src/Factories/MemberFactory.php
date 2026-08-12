<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Framework\Tests\Factories\HasSiteId;
use App\Models\Member;
use App\Tests\Support\TestPassword;

class MemberFactory extends Factory
{
    use HasSiteId;

    protected function model(): string
    {
        return Member::class;
    }

    protected function definition(): array
    {
        return [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'user' . uniqid() . '@example.com',
            'password' => TestPassword::HASH,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function withEmail(string $email): static
    {
        return $this->state(['email' => $email]);
    }

    public function named(string $name): static
    {
        return $this->state(['name' => $name]);
    }
}