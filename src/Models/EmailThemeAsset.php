<?php

namespace App\Models;

class EmailThemeAsset extends Model
{
    protected $table = 'email_theme_assets';

    protected $fillable = [
        'theme_id',
        'asset_key',
        'asset_type',
        'asset_url',
        'alt_text',
        'width',
        'height'
    ];

    public function theme()
    {
        return $this->belongsTo(EmailTheme::class, 'theme_id');
    }
}