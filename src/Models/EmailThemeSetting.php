<?php

namespace App\Models;

class EmailThemeSetting extends Model
{
    protected $table = 'email_theme_settings';

    protected $fillable = [
        'theme_id',
        'setting_key',
        'setting_value',
        'setting_type'
    ];

    public function theme()
    {
        return $this->belongsTo(EmailTheme::class, 'theme_id');
    }
}