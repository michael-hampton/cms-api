<?php

namespace App\Repositories\Cms\Briefs;

use App\Models\BriefTemplate;
use App\Repositories\Repository;

class BriefTemplateRepository extends Repository
{
    public function getForSite(int $siteId): array
    {
        return BriefTemplate::where('site_id', $siteId)
            ->orWhere('is_system', true)
            ->with(['creator'])
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function createSystemTemplates(int $siteId): void
    {
        $templates = [
            [
                'name' => 'Product Review',
                'description' => 'Comprehensive product review with pros/cons',
                'type' => 'review',
                'default_fields' => [
                    'target_word_count' => 1500,
                    'seo_keywords' => 'review, product name, best',
                    'description' => "Introduction\n\nProduct Overview\n\nPros\n\nCons\n\nVerdict"
                ]
            ],
            [
                'name' => 'Listicle',
                'description' => 'List-based article format',
                'type' => 'listicle',
                'default_fields' => [
                    'target_word_count' => 2000,
                    'description' => "Introduction\n\n1. First Item\n\n2. Second Item\n\nConclusion"
                ]
            ],
            [
                'name' => 'How-To Guide',
                'description' => 'Step-by-step instructional content',
                'type' => 'howto',
                'default_fields' => [
                    'target_word_count' => 1200,
                    'description' => "Introduction\n\nWhat You'll Need\n\nStep 1:\n\nStep 2:\n\nConclusion"
                ]
            ],
            [
                'name' => 'Buying Guide',
                'description' => 'Comprehensive purchasing guide',
                'type' => 'guide',
                'default_fields' => [
                    'target_word_count' => 2500,
                    'description' => "Introduction\n\nWhat to Look For\n\nTop Picks\n\nBuying Tips\n\nConclusion"
                ]
            ]
        ];

        foreach ($templates as $template) {
            BriefTemplate::create([
                'site_id' => $siteId,
                'is_system' => true,
                'created_by' => 1, // System user
                ...$template
            ]);
        }
    }

    protected function getModelClass(): string
    {
        return BriefTemplate::class;
    }
}