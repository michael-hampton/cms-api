<?php

namespace App\Database\Seeders;

use App\Enums\Cms\CustomFieldContext;
use App\Enums\Cms\CustomFieldStorageType;
use App\Models\CustomFieldDefinition;
use App\Models\Site;

/**
 * Seeds contributor profile field definitions for every site.
 *
 * Idempotent: existing admin choices for unlocked fields are preserved on
 * repeat runs. Only locked fields are unconditionally forced to active+required.
 * Newly added fields in the seed list are created for existing sites.
 */
class ContributorProfileCustomFieldDefinitionSeeder
{
    public function run(): void
    {
        $fields = $this->fieldDefinitions();

        foreach (Site::all() as $site) {
            foreach ($fields as $field) {
                $definition = CustomFieldDefinition::firstOrNew([
                    'site_id' => $site->id,
                    'context' => CustomFieldContext::ContributorProfile->value,
                    'key'     => $field['key'],
                ]);

                $isNew = !$definition->exists;

                // Structural columns — always set, even on update, so the schema stays correct.
                $definition->type         = $field['type'];
                $definition->context      = CustomFieldContext::ContributorProfile->value;
                $definition->storage_type = CustomFieldStorageType::ProfileColumn->value;
                $definition->profile_column = $field['profile_column'];
                $definition->is_locked    = $field['is_locked'];
                $definition->group_name   = 'open_collab_contributor_profile';

                // Preserve admin choices for these on existing rows.
                if ($isNew) {
                    $definition->name             = $field['name'];
                    $definition->description      = $field['description'] ?? null;
                    $definition->placeholder      = $field['placeholder'] ?? null;
                    $definition->options          = isset($field['options']) ? json_encode($field['options']) : null;
                    $definition->validation_rules = $field['validation_rules'] ?? null;
                    $definition->default_value    = $field['default_value'] ?? null;
                    $definition->is_required      = $field['is_required'];
                    $definition->is_active        = true;
                    $definition->is_searchable    = false;
                    $definition->sort_order       = $field['sort_order'];
                } else {
                    // Only restore the sort_order hint if the admin hasn't set one.
                    $definition->sort_order = $definition->sort_order ?: $field['sort_order'];
                    $definition->name       = $definition->name ?: $field['name'];
                }

                // Locked fields are always active and required — admin cannot override.
                if ($definition->is_locked) {
                    $definition->is_active   = true;
                    $definition->is_required = true;
                }

                $definition->save();
            }
        }
    }

    // ── Field definitions ─────────────────────────────────────────────────────

    private function fieldDefinitions(): array
    {
        return [
            [
                'name'           => 'Display Name',
                'key'            => 'display_name',
                'type'           => 'text',
                'description'    => 'Public display name shown on articles.',
                'placeholder'    => 'Enter your public display name',
                'profile_column' => 'display_name',
                'is_required'    => true,
                'is_locked'      => false,
                'sort_order'     => 30,
            ],
            [
                'name'           => 'Bio',
                'key'            => 'bio',
                'type'           => 'textarea',
                'description'    => 'Short contributor biography.',
                'placeholder'    => 'Tell readers about yourself',
                'profile_column' => 'bio',
                'is_required'    => true,
                'is_locked'      => false,
                'sort_order'     => 40,
            ],
            [
                'name'           => 'Profile Image',
                'key'            => 'avatar',
                'type'           => 'image',
                'description'    => 'Contributor profile image.',
                'placeholder'    => null,
                'profile_column' => 'avatar',
                'is_required'    => false,
                'is_locked'      => false,
                'sort_order'     => 50,
            ],
            [
                'name'           => 'Country of tax residence',
                'key'            => 'tax_country',
                'type'           => 'select',
                'description'    => 'Used for tax reporting purposes only.',
                'placeholder'    => null,
                'profile_column' => 'tax_country',
                'is_required'    => false,
                'is_locked'      => false,
                'sort_order'     => 60,
                'options'        => [
                    ['label' => 'United Kingdom', 'value' => 'GB'],
                    ['label' => 'United States',  'value' => 'US'],
                    ['label' => 'Canada',         'value' => 'CA'],
                    ['label' => 'Australia',      'value' => 'AU'],
                    ['label' => 'Germany',        'value' => 'DE'],
                    ['label' => 'France',         'value' => 'FR'],
                    ['label' => 'Ireland',        'value' => 'IE'],
                    ['label' => 'Netherlands',    'value' => 'NL'],
                ],
            ],
            [
                'name'           => 'Timezone',
                'key'            => 'timezone',
                'type'           => 'text',
                'description'    => 'Contributor timezone.',
                'placeholder'    => 'Enter your timezone',
                'profile_column' => 'timezone',
                'is_required'    => false,
                'is_locked'      => false,
                'sort_order'     => 70,
            ],
            [
                'name'           => 'Areas of Expertise',
                'key'            => 'expertise',
                'type'           => 'multi_select',
                'description'    => 'Topics the contributor can write about.',
                'placeholder'    => null,
                'profile_column' => 'expertise',
                'is_required'    => false,
                'is_locked'      => false,
                'sort_order'     => 80,
                'options'        => [
                    ['label' => 'News',    'value' => 'news'],
                    ['label' => 'Reviews', 'value' => 'reviews'],
                    ['label' => 'Guides',  'value' => 'guides'],
                    ['label' => 'Opinion', 'value' => 'opinion'],
                ],
            ],
            [
                'name'           => 'Portfolio URL',
                'key'            => 'portfolio_url',
                'type'           => 'url',
                'description'    => 'Link to contributor portfolio.',
                'placeholder'    => 'https://example.com',
                'profile_column' => 'portfolio_url',
                'is_required'    => false,
                'is_locked'      => false,
                'sort_order'     => 90,
            ],
            [
                'name'           => 'Writing Samples',
                'key'            => 'writing_samples',
                'type'           => 'json',
                'description'    => 'Links to previous writing samples.',
                'placeholder'    => null,
                'profile_column' => 'sample_links',
                'is_required'    => false,
                'is_locked'      => false,
                'sort_order'     => 100,
            ],
            [
                'name'           => 'LinkedIn URL',
                'key'            => 'linkedin_url',
                'type'           => 'url',
                'description'    => 'Contributor LinkedIn profile URL.',
                'placeholder'    => 'https://linkedin.com/in/example',
                'profile_column' => 'linkedin_url',
                'is_required'    => false,
                'is_locked'      => false,
                'sort_order'     => 110,
            ],
            [
                'name'           => 'Instagram URL',
                'key'            => 'instagram_url',
                'type'           => 'url',
                'description'    => 'Contributor Instagram profile URL.',
                'placeholder'    => 'https://instagram.com/example',
                'profile_column' => 'instagram_url',
                'is_required'    => false,
                'is_locked'      => false,
                'sort_order'     => 120,
            ],
            [
                'name'           => 'Twitter URL',
                'key'            => 'twitter_url',
                'type'           => 'url',
                'description'    => 'Contributor Twitter/X profile URL.',
                'placeholder'    => 'https://x.com/example',
                'profile_column' => 'twitter_url',
                'is_required'    => false,
                'is_locked'      => false,
                'sort_order'     => 125,
            ],
            [
                'name'           => 'TikTok URL',
                'key'            => 'tiktok_url',
                'type'           => 'url',
                'description'    => 'Contributor TikTok profile URL.',
                'placeholder'    => 'https://tiktok.com/@example',
                'profile_column' => 'tiktok_url',
                'is_required'    => false,
                'is_locked'      => false,
                'sort_order'     => 130,
            ],
        ];
    }
}
