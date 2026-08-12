<?php

namespace App\Factories;

use App\Framework\Tests\Factories\Factory;
use App\Framework\Tests\Factories\HasSiteId;
use App\Models\User;
use App\Tests\Support\TestPassword;

class UserFactory extends Factory
{
    use HasSiteId;

    protected function model(): string
    {
        return User::class;
    }

    protected function definition(): array
    {
        return [
            'name' => 'Test User',
            'email' => 'user' . uniqid() . '@example.com',
            'password' => TestPassword::HASH,
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