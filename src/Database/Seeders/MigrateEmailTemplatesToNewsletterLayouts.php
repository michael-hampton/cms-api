<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\EmailTemplate;
use App\Models\NewsletterLayout;

class MigrateEmailTemplatesToNewsletterLayouts extends Seeder
{

    public function run(): void
    {
        $templates = EmailTemplate::all();

        // Map old email_template id → new newsletter_layout id for version migration
        $idMap = [];

        foreach ($templates as $tpl) {
            $definition = [
                'schema_version' => 1,
                'regions' => [],
                'email_template' => [
                    'category' => $tpl->category ?? 'transactional',
                    'use_default_theme' => (bool)($tpl->use_default_theme ?? true),
                    'theme_id' => $tpl->theme_id ?? null,
                    'description' => $tpl->description ?? null,
                    'is_active' => (bool)($tpl->is_active ?? true),
                    'blocks' => $tpl->blocks ?? [],
                ],
            ];

            $layout = NewsletterLayout::create([
                'site_id' => $tpl->site_id,
                'category' => $tpl->category ?? '',
                'description' => $tpl->description ?? '',
                'type' => 'email_template',
                'name' => $tpl->name,
                'slug' => $tpl->slug,
                'layout_definition_json' => json_encode($definition),
                'is_system_layout' => false,
                'created_by' => null,
                // Preserve original timestamps if the layouts table has them;
                // fall back to now() if not.
                'created_at' => $tpl->created_at ?? date('Y-m-d H:i:s'),
                'updated_at' => $tpl->updated_at ?? date('Y-m-d H:i:s'),
            ]);

            $newLayoutId = $layout->id;
            $idMap[$tpl->id] = $newLayoutId;
        }
    }
}