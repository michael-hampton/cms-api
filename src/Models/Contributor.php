<?php

namespace App\Models;

class Contributor extends Model
{
    protected $table = 'oc_contributors';

    protected $fillable = [
        'name',
        'email',
        'password',
        'stripe_account_id',
        'is_active',
        'site_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function verifyPassword(string $plain): bool
    {
        return password_verify($plain, $this->password);
    }

    public function isActive(): bool
    {
        return (bool)$this->is_active;
    }
}