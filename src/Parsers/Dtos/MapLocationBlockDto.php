<?php

namespace App\Parsers\Dtos;

final class MapLocationBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = [
        'latitude', 'longitude', 'zoom', 'title', 'address', 'description',
        'showMarker', 'markerIcon', 'mapType'
    ];

    private const ALLOWED_MAP_TYPES = ['roadmap', 'satellite', 'hybrid', 'terrain'];

    public function __construct(
        public float   $latitude,
        public float   $longitude,
        public int     $zoom,
        public string  $title,
        public string  $address,
        public string  $description,
        public bool    $showMarker,
        public ?string $markerIcon,
        public string  $mapType,
        public int     $height
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'latitude' => 0.0,
            'longitude' => 0.0,
            'zoom' => 15,
            'title' => '',
            'address' => '',
            'description' => '',
            'showMarker' => true,
            'markerIcon' => null,
            'mapType' => 'roadmap'
        ]);

        return new self(
            (float)$data['latitude'],
            (float)$data['longitude'],
            (int)$data['zoom'],
            trim($data['title']),
            trim($data['address']),
            trim($data['description']),
            (bool)$data['showMarker'],
            $data['markerIcon'],
            self::validateEnum($data['mapType'], self::ALLOWED_MAP_TYPES, 'roadmap', 'mapType'),
            (int)$data['height']
        );
    }

    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'zoom' => $this->zoom,
            'title' => $this->title,
            'address' => $this->address,
            'description' => $this->description,
            'showMarker' => $this->showMarker,
            'markerIcon' => $this->markerIcon,
            'mapType' => $this->mapType,
            'has_title' => !empty($this->title),
            'has_description' => !empty($this->description),
            'coordinates' => "{$this->latitude},{$this->longitude}",
            'height' => $this->height
        ];
    }

    public function getType(): string
    {
        return 'map-location';
    }
}