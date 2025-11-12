<?php

namespace App\Repositories;

use App\Models\DealAlert;

class DealAlertRepository extends Repository
{
    public function findByEmail(string $email): ?DealAlert
    {
        return DealAlert::where('email', $email)
            ->where('is_active', true)
            ->first();
    }

    public function findByToken(string $token): ?DealAlert
    {
        return DealAlert::where('verification_token', $token)->first();
    }

    public function getActiveAlerts(): array
    {
        return DealAlert::where('is_active', true)
            ->whereNotNull('verified_at')
            ->get()
            ->toArray();
    }

    public function getUnverifiedAlerts(): array
    {
        return DealAlert::where('is_active', true)
            ->whereNull('verified_at')
            ->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-24 hours')))
            ->get()
            ->toArray();
    }

    public function getTotalCount(): int
    {
        return DealAlert::count();
    }

    public function getActiveCount(): int
    {
        return DealAlert::where('is_active', true)
            ->whereNotNull('verified_at')
            ->count();
    }

    protected function getModelClass(): string
    {
        return DealAlert::class;
    }
}