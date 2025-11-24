<?php

namespace App\Models;

class EmailThemeFont extends Model
{
    protected $table = 'email_theme_fonts';

    protected $fillable = [
        'theme_id',
        'font_key',
        'font_family',
        'font_size',
        'font_weight'
    ];

    public function theme()
    {
        return $this->belongsTo(EmailTheme::class, 'theme_id');
    }
}