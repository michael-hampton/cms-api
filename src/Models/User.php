<?php

namespace App\Models;

class User extends Model
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'site_id',
        'avatar',
        'is_contributor'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
        'is_contributor' => 'boolean'
    ];

    public $table = 'users';

    public function verifyPassword(string $password)
    {
        return password_verify($password, $this->attributes['password'] ?? '');
    }

    public function isActive()
    {
        return $this->is_active;
    }
}