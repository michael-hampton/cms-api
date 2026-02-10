<?php

namespace App\Parsers;

// MapLocationBlockParser.php
namespace App\Parsers;

use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MaxRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\NumericRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Parsers\Dtos\MapLocationBlockDto;
use App\Parsers\Renderers\MapLocationBlockRenderer;

class MapLocationBlockParser extends BaseBlockParser
{
    private MapLocationBlockRenderer $renderer;

    public function __construct()
    {
        $this->renderer = new MapLocationBlockRenderer();
    }
    public function getType(): string
    {
        return 'map-location';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [new MaxLengthRule(255)],
            'address' => [new RequiredRule(), new MaxLengthRule(500)],
            'latitude' => [new NumericRule()],
            'longitude' => [new NumericRule()],
            'zoom' => [new RequiredRule(), new MinRule(1), new MaxRule(20)],
            'mapType' => [new RequiredRule(), new MaxLengthRule(50)],
            'showMarker' => [new BooleanRule()],
            'height' => [new RequiredRule(), new MinRule(100)],
            'description' => [new MaxLengthRule(1000)]
        ];
    }

    public function parse(array $data): array
    {
        // Build DTO (handles normalization + structural validation)
        $dto = MapLocationBlockDto::fromArray($data);

        // Return array for legacy compatibility
        return $dto->toArray();
    }

    public function generateHtml(array $parsedData): string
    {
        $dto = MapLocationBlockDto::fromArray($parsedData);
        return $this->renderer->render($dto);
    }
}