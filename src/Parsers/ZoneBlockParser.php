<?php
// src/Parsers/ZoneBlockParser.php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\InRule;
use App\Framework\Validation\Rules\IntegerRule;
use App\Framework\Validation\Rules\MaxRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Models\Page;
use App\Repositories\BlockRepository;
use App\Services\BlockParserService;

class ZoneBlockParser extends BaseBlockParser
{
    private BlockRepository $blockRepository;
    private BlockParserService $blockParserService;

    public function __construct(
        BlockRepository    $blockRepository,
        BlockParserService $blockParserService
    )
    {
        $this->blockRepository = $blockRepository;
        $this->blockParserService = $blockParserService;
    }

    public function getType(): string
    {
        return 'zone';
    }

    public function getValidationRules(): array
    {
        return [
            'id' => [new RequiredRule()],
            'name' => [new RequiredRule()],
            'columns' => [
                new RequiredRule(),
                new IntegerRule(),
                new MinRule(1),
                new MaxRule(4)
            ],
            'blocks' => [
                new RequiredRule(),
                new ArrayRule()
            ],
            'options' => [new ArrayRule()],
            'options.background' => [
                new InRule(['default', 'muted', 'brand'])
            ],
            'options.padding' => [
                new InRule(['small', 'medium', 'large'])
            ],
            'options.width' => [
                new InRule(['contained', 'full'])
            ],
            'sortOrder' => [new IntegerRule()]
        ];
    }

    public function parse(array $data): array
    {
        return [
            'id' => $data['id'],
            'name' => trim($data['name'] ?? ''),
            'columns' => (int)($data['columns'] ?? 1),
            'blocks' => $data['blocks'] ?? [],
            'options' => $this->parseOptions($data['options'] ?? []),
            'sortOrder' => (int)($data['sortOrder'] ?? 0)
        ];
    }

    private function parseOptions(array $options): array
    {
        return [
            'background' => $options['background'] ?? 'default',
            'padding' => $options['padding'] ?? 'medium',
            'width' => $options['width'] ?? 'contained'
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        return ''; // Zones don't generate their own HTML
    }

    /**
     * Build HTML for all zones on a page
     */
    public function buildZonesHtml(Page $page): array
    {
        $zones = $this->getPageZones($page);

        if (empty($zones)) {
            return [
                'html' => '',
                'usedBlockIds' => []
            ];
        }

        // Get all blocks for the page keyed by ID
        $pageBlocks = $this->blockRepository->getPageBlocks($page->id);

        $blocksById = [];
        foreach ($pageBlocks as $block) {
            $blocksById[$block->id] = $block;
        }

        $usedBlockIds = [];
        $zonesHtml = [];

        // Sort zones by sortOrder
        usort($zones, function ($a, $b) {
            return ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0);
        });

        foreach ($zones as $zone) {
            $zoneHtml = $this->buildZoneHtml($zone, $blocksById, $usedBlockIds);
            if (!empty($zoneHtml)) {
                $zonesHtml[] = $zoneHtml;
            }
        }

        return [
            'html' => implode("\n", $zonesHtml),
            'usedBlockIds' => array_unique(array_keys($usedBlockIds))
        ];
    }

    private function getPageZones(Page $page): array
    {
        if (!$page->zones) {
            return [];
        }

        $zones = is_string($page->zones) ? json_decode($page->zones, true) : $page->zones;

        return is_array($zones) ? $zones : [];
    }

    private function buildZoneHtml(array $zone, array $blocksById, array &$usedBlockIds): string
    {
        $options = $zone['options'] ?? [];
        $background = $options['background'] ?? 'default';
        $padding = $options['padding'] ?? 'medium';
        $width = $options['width'] ?? 'contained';
        $columns = $zone['columns'] ?? 1;

        $html = "<div class=\"zone zone-{$zone['id']} zone-background-{$background} zone-padding-{$padding} zone-width-{$width}\">";

        if ($width === 'contained') {
            $html .= "<div class=\"zone-container\">";
        }

        $html .= "<div class=\"zone-grid zone-columns-{$columns}\">";

        // Process each column
        $blockColumns = $zone['blocks'] ?? [];

        foreach ($blockColumns as $columnIndex => $columnBlocks) {
            $html .= "<div class=\"zone-column zone-column-{$columnIndex}\">";

            if (is_array($columnBlocks)) {
                foreach ($columnBlocks as $blockId) {
                    if ($blockId === null || isset($usedBlockIds[$blockId])) {
                        continue; // Skip nulls and duplicates
                    }

                    if (isset($blocksById[$blockId])) {
                        $block = $blocksById[$blockId];

                        try {
                            $blockHtml = $this->blockParserService->buildBlock(
                                $block->page_id,
                                array_merge($block->data, ['type' => $block->type]),
                                0,
                                $block->id
                            );

                            $html .= $blockHtml;
                            $usedBlockIds[$blockId] = true;
                        } catch (\Exception $e) {
                            // Log error but continue processing
                            error_log("Failed to render block {$blockId} in zone: {$e->getMessage()}");
                        }
                    }
                }
            }

            $html .= "</div>";
        }

        $html .= "</div>"; // zone-grid

        if ($width === 'contained') {
            $html .= "</div>"; // zone-container
        }

        $html .= "</div>"; // zone

        return $html;
    }
}