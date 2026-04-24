<?php

namespace App\Repositories\Cms;

use App\Models\EmailThemeSetting;
use App\Repositories\Repository;

class EmailThemeSettingRepository extends Repository
{
    public function getSettingsForTheme(int $themeId)
    {
        return EmailThemeSetting::where('theme_id', $themeId)->get();
    }

    public function deleteSettingsForTheme(int $themeId)
    {
        return EmailThemeSetting::where('theme_id', $themeId)->delete();
    }

    protected function getModelClass(): string
    {
        return EmailThemeSetting::class;
    }
}