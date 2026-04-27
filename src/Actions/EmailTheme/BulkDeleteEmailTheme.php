<?php

namespace App\Actions\EmailTheme;

use App\Framework\Database\Database;
use App\Models\NewsletterBrandingConfiguration;
use App\Services\Cms\ImageUploadService;

/**
 * Bulk-deletes NewsletterBrandingConfiguration records (email_template type).
 *
 * Rules carried over from the original EmailTheme implementation:
 *   - Default themes are skipped (cannot be deleted while they are the default)
 *   - Logo assets stored via ImageUploadService are cleaned up before deletion
 *   - Each deletion is attempted individually so a single failure does not
 *     abort the remaining records
 *
 * Returns:
 *   deleted[] — ids that were successfully deleted
 *   failed[]  — ids that could not be deleted, with the reason
 */
class BulkDeleteEmailTheme
{
    public function __construct(
        private readonly ImageUploadService $imageUploadService,
        private readonly Database           $db,
    )
    {
    }

    public function handle(array $ids): array
    {
        $deleted = [];
        $failed = [];

        foreach ($ids as $id) {
            try {
                $result = $this->deleteSingle((int)$id);

                if ($result['success']) {
                    $deleted[] = $id;
                } else {
                    $failed[] = ['id' => $id, 'reason' => $result['reason']];
                }
            } catch (\Throwable $e) {
                $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        return compact('deleted', 'failed');
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function deleteSingle(int $id): array
    {
        return $this->db->transaction(function () use ($id) {
            $theme = NewsletterBrandingConfiguration::find($id);

            if (!$theme) {
                return ['success' => false, 'reason' => "Theme {$id} not found."];
            }

            if ($theme->is_default) {
                return [
                    'success' => false,
                    'reason' => "Theme {$id} is the site default and cannot be deleted.",
                ];
            }

            // Remove logo asset from storage if present
            $assets = $theme->getAssets();
            if (!empty($assets['logo']['url'])) {
                try {
                    $this->imageUploadService->delete($assets['logo']['url']);
                } catch (\Throwable) {
                    // Asset cleanup failure must not block the record deletion
                }
            }

            $theme->delete();

            return ['success' => true];
        });
    }
}