<?php

namespace App\Models;

class Person extends Model
{
    protected $table = 'persons';
    protected $fillable = ['name', 'role', 'email', 'phone', 'bio', 'image'];
}