<?php

namespace App\Models;

class EmailThemeColor extends Model
{
    protected $table = 'email_theme_colors';

    protected $fillable = [
        'theme_id',
        'color_key',
        'color_value'
    ];

    public function theme()
    {
        return $this->belongsTo(EmailTheme::class, 'theme_id');
    }
}