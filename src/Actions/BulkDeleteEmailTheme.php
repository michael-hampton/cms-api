<?php

namespace App\Actions;

use App\Framework\Database\Database;
use App\Models\EmailTheme;
use App\Services\Cms\ImageUploadService;

class BulkDeleteEmailTheme
{
    public function __construct(
        private Database           $db,
        private ImageUploadService $imageUploadService
    )
    {
    }

    public function handle(array $themeIds): array
    {
        $deleted = [];
        $failed = [];

        foreach ($themeIds as $themeId) {
            try {
                $this->db->transaction(function () use ($themeId) {
                    $theme = EmailTheme::find($themeId);

                    if (!$theme) {
                        throw new \Exception("Theme not found");
                    }

                    // Cannot delete default theme
                    if ($theme->is_default) {
                        throw new \Exception("Cannot delete default theme");
                    }

                    // Delete logo if exists
                    $assets = $theme->getAssets();
                    if (isset($assets['logo']['url'])) {
                        try {
                            $this->imageUploadService->delete($assets['logo']['url']);
                        } catch (\Exception $e) {
                            // Continue even if file deletion fails
                        }
                    }

                    // Delete theme (cascades to colors, fonts, assets, settings)
                    $theme->delete();
                });

                $deleted[] = [
                    'id' => $themeId,
                    'message' => 'Deleted successfully'
                ];

            } catch (\Exception $e) {
                $failed[] = [
                    'id' => $themeId,
                    'reason' => $e->getMessage()
                ];
            }
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed
        ];
    }
}