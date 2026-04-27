<?php

namespace App\Actions\EmailTheme;

use App\Framework\Database\Database;
use App\Framework\Support\Str;
use App\Models\NewsletterBrandingConfiguration;
use App\Repositories\Newsletters\NewsletterBrandingRepository;
use App\Resources\EmailThemeResource;

/**
 * Clones a NewsletterBrandingConfiguration (email_template type) into a new record.
 *
 * The clone receives:
 *   - a new name (caller-supplied or auto-generated)
 *   - a unique slug derived from the new name
 *   - is_default = false  (clones never start as default)
 *   - the full theme_json from the source record
 *   - clone_history updated to include the source id
 */
class CloneEmailTheme
{
    public function __construct(
        private readonly NewsletterBrandingRepository $brandingRepository,
        private readonly Database                     $db,
    )
    {
    }

    /**
     * @throws \RuntimeException when the source theme is not found
     */
    public function handle(int $sourceId, ?string $newName = null): array
    {
        return $this->db->transaction(function () use ($sourceId, $newName) {
            $source = NewsletterBrandingConfiguration::find($sourceId);

            if (!$source) {
                throw new \RuntimeException("Email theme {$sourceId} not found.");
            }

            $name = $newName ?? ($source->name . ' (Copy)');
            $slug = $this->ensureUniqueSlug(Str::slug($name), $source->site_id);

            // Build clone_history: append the source id to whatever the source already carries
            $history = $source->clone_history ?? [];
            $history[] = [
                'cloned_from' => $source->id,
                'cloned_at' => date('Y-m-d H:i:s'),
            ];

            $clone = NewsletterBrandingConfiguration::create([
                'site_id' => $source->site_id,
                'newsletter_id' => null,
                'name' => $name,
                'slug' => $slug,
                'description' => $source->description,
                'is_active' => $source->is_active,
                'is_default' => false,
                'type' => NewsletterBrandingConfiguration::TYPE_EMAIL_TEMPLATE,
                'theme_json' => $source->theme_json,
                'logo_url' => $source->logo_url,
                'header_text' => $source->header_text,
                'footer_text' => $source->footer_text,
                'custom_css' => $source->custom_css,
                'clone_history' => $history,
            ]);

            return [
                'success' => true,
                'theme' => EmailThemeResource::make($clone)->toArray(),
            ];
        });
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function ensureUniqueSlug(string $slug, int $siteId): string
    {
        $base = $slug;
        $counter = 1;

        while ($this->brandingRepository->slugExistsForSite($slug, $siteId)) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }
}