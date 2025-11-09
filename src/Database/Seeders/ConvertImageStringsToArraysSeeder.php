<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Block;
use App\Models\Page;

class ConvertImageStringsToArraysSeeder extends Seeder
{
    private int $blocksUpdated = 0;
    private int $imagesConverted = 0;

    public function run(): void
    {
        echo "Starting image string to array conversion...\n\n";

        $pages = Page::with(['blocks'])->get();

        foreach ($pages as $page) {
            echo "Processing page: {$page->title} (ID: {$page->id})\n";

            foreach ($page->blocks as $block) {
                $this->processBlock($block);
            }
        }

        echo "\n========================================\n";
        echo "Conversion Complete!\n";
        echo "Blocks updated: {$this->blocksUpdated}\n";
        echo "Images converted: {$this->imagesConverted}\n";
        echo "========================================\n";
    }

    private function processBlock(Block $block): void
    {
        $data = $block->data;

        if (!$data) {
            return;
        }

        $originalData = $data;
        $blockUpdated = false;

        // Recursively search for 'image' keys and convert strings to arrays
        $data = $this->convertImageStrings($data, $blockUpdated);

        // Only update if changes were made
        if ($blockUpdated && $data !== $originalData) {
            $block->data = json_encode($data);
            $block->save();
            $this->blocksUpdated++;
            echo "  - Updated block ID {$block->id} (type: {$block->type})\n";
        }
    }

    private function convertImageStrings($data, &$blockUpdated): mixed
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                // Check if this is an 'image' key with a string value (URL)
                if ($key === 'image' && is_string($value) && !empty($value)) {
                    // Convert string to array format
                    $data[$key] = [
                        'src' => $value,
                        'alt' => ''
                    ];
                    $blockUpdated = true;
                    $this->imagesConverted++;
                    echo "    → Converted image string to array: " . substr($value, 0, 60) . "...\n";
                }
                // Also handle cases where 'src' is directly a string at top level
                elseif ($key === 'src' && is_string($value) && !empty($value)) {
                    // Check if parent array looks like it should be an image object
                    if (!isset($data['alt'])) {
                        $data['alt'] = '';
                        $blockUpdated = true;
                    }
                }
                // Recursively process nested arrays
                elseif (is_array($value)) {
                    $data[$key] = $this->convertImageStrings($value, $blockUpdated);
                }
            }
        }

        return $data;
    }
}