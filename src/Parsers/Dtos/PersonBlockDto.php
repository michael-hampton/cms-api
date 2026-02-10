<?php

namespace App\Parsers\Dtos;

final class PersonBlockDto extends BaseBlockDto
{
    private const ALLOWED_DISPLAY_TYPES = ['profile', 'contact', 'card'];
    private const ALLOWED_CONTEXTS = ['default', 'sidebar'];

    private const KNOWN_KEYS = [
        'name'
    ];

    public function __construct(
        public string  $name,
        public string  $role,
        public string  $strapline,
        public string  $bio,
        public ?array  $image,
        public ?string $phone,
        public bool    $enableSchema,
        public ?string $email,
        public ?string $twitter,
        public ?string $website,
        public ?string $instagram,
        public ?string $facebook,
        public ?string $linkedin,
        public ?string $tiktok,
        public ?string $youtube,
        public string  $displayType,
        public string  $context,
        public string  $address,
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'name' => '',
            'role' => '',
            'strapline' => '',
            'bio' => '',
            'image' => null,
            'phone' => null,
            'enableSchema' => false,
            'email' => null,
            'twitter' => null,
            'website' => null,
            'instagram' => null,
            'facebook' => null,
            'linkedin' => null,
            'tiktok' => null,
            'youtube' => null,
            'displayType' => 'profile',
            'context' => 'default',
            'address' => ''
        ]);

        return new self(
            trim($data['name']),
            trim($data['role']),
            trim($data['strapline']),
            trim($data['bio']),
            $data['image'],
            $data['phone'],
            (bool)$data['enableSchema'],
            $data['email'],
            $data['twitter'],
            $data['website'],
            $data['instagram'],
            $data['facebook'],
            $data['linkedin'],
            $data['tiktok'],
            $data['youtube'],
            self::validateEnum($data['displayType'], self::ALLOWED_DISPLAY_TYPES, 'profile', 'displayType'),
            self::validateEnum($data['context'], self::ALLOWED_CONTEXTS, 'default', 'context'),
            $data['address'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'role' => $this->role,
            'strapline' => $this->strapline,
            'bio' => $this->bio,
            'image' => $this->image,
            'phone' => $this->phone,
            'enableSchema' => $this->enableSchema,
            'email' => $this->email,
            'twitter' => $this->twitter,
            'website' => $this->website,
            'instagram' => $this->instagram,
            'facebook' => $this->facebook,
            'linkedin' => $this->linkedin,
            'tiktok' => $this->tiktok,
            'youtube' => $this->youtube,
            'bio_word_count' => str_word_count(strip_tags($this->bio)),
            'strapline_word_count' => str_word_count($this->strapline),
            'formatted_bio' => nl2br(htmlspecialchars($this->bio)),
            'formatted_name' => htmlspecialchars($this->name),
            'formatted_role' => htmlspecialchars($this->role),
            'formatted_strapline' => htmlspecialchars($this->strapline),
            'social_links' => $this->getSocialLinks(),
            'displayType' => $this->displayType,
            'context' => $this->context,
            'address' => $this->address,
        ];
    }

    public function getSocialLinks(): array
    {
        $links = [];
        $socialFields = [
            'twitter', 'instagram', 'facebook', 'linkedin', 'tiktok', 'youtube'
        ];

        foreach ($socialFields as $field) {
            $value = $this->$field;
            if ($value) {
                $links[$field] = $value;
            }
        }

        if ($this->website) {
            $links['website'] = $this->website;
        }

        return $links;
    }

    public function getType(): string
    {
        return 'person';
    }
}