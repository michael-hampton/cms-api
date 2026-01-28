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
        'avatar'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    public $table = 'users';

    public function verifyPassword(string $password)
    {
        return true; //todo
    }

    public function isActive()
    {
        return true; //todo
    }
}