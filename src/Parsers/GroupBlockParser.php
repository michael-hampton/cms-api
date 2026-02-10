<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\GroupLayoutRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredIfRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\GroupBlockDto;
use App\Parsers\Renderers\GroupBlockRenderer;

class GroupBlockParser extends BaseBlockParser
{
    private GroupBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new GroupBlockRenderer();
    }
    public function getType(): string
    {
        return 'group';
    }

    public function getValidationRules(): array
    {
        return [
            'layout' => [
                new RequiredRule(),
                //new GroupLayoutRule()
            ],
            'blocks' => [
                new RequiredRule(),
                new ArrayRule()
            ],
            'carouselTitle' => [
                new RequiredIfRule('layout', 'carousel'),
                new MaxLengthRule(200)
            ]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = GroupBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }


    public function generateHtml(array $parsedData): string
    {
        $dto = GroupBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }

    public function validateSpotlightLayout(array $blocks): array
    {
        $errors = [];

        // Check for at least one image block
        $hasImageBlock = false;
        foreach ($blocks as $block) {
            if ($block['type'] === 'image') {
                $hasImageBlock = true;
                break;
            }
        }

        if (!$hasImageBlock) {
            $errors[] = 'Spotlight layout requires at least one image block';
        }

        // Recommended: 1 image + 2-5 product blocks
        $productCount = count(array_filter($blocks, function ($block) {
            return $block['type'] === 'product';
        }));

        if ($productCount === 0) {
            $errors[] = 'Spotlight layout works best with product blocks';
        } elseif ($productCount > 5) {
            $errors[] = 'Spotlight layout recommended maximum is 5 product blocks';
        }

        return $errors;
    }

    public function extractSpotlightData(array $blocks): array
    {
        $imageBlock = null;
        $contentBlocks = [];

        foreach ($blocks as $block) {
            if ($block['type'] === 'image' && $imageBlock === null) {
                $imageBlock = $block;
            } else {
                $contentBlocks[] = $block;
            }
        }

        return [
            'image_block' => $imageBlock,
            'content_blocks' => $contentBlocks,
            'has_image' => $imageBlock !== null,
            'content_block_count' => count($contentBlocks)
        ];
    }
}