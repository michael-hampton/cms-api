<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Block;
use App\Models\Page;

class AddReviewDataToPages extends Seeder
{
    private const array PLACEHOLDER_PROS = [
        'Impressive build quality',
        'Strong day-to-day performance',
        'Great value for the price',
    ];

    private const array PLACEHOLDER_CONS = [
        'Limited colour options',
        'Battery life could be better',
    ];

    private const string PLACEHOLDER_VERDICT =
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit. A solid, well-rounded option that is easy to recommend for most buyers.';

    public function run(): void
    {
        foreach (Block::where('type', 'award')->get() as $block) {
            $page = Page::find($block->page_id);

            if (!$page || $page->review_data) {
                continue;
            }

            $data = is_array($block->data)
                ? $block->data
                : (json_decode((string) $block->data, true) ?: []);

            $page->review_data = [
                'rating' => (float) ($data['rating'] ?? 0),
                'max_rating' => 5,
                'product' => $data['productName'] ?? null,
                'category' => $data['subcategory'] ?? null,
                'verdict' => self::PLACEHOLDER_VERDICT,
                'pros' => self::PLACEHOLDER_PROS,
                'cons' => self::PLACEHOLDER_CONS,
            ];
            $page->save();
        }
    }
}