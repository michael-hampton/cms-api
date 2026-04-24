<?php

namespace App\Repositories\Cms;

use App\Models\EmailThemeAsset;
use App\Repositories\Repository;

class EmailThemeAssetRepository extends Repository
{
    public function deleteAssetsForTheme(int $themeId)
    {
        return EmailThemeAsset::where('theme_id', $themeId)->delete();
    }

    protected function getModelClass(): string
    {
        return EmailThemeAsset::class;
    }
}