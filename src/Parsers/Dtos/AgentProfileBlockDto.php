<?php

namespace App\Parsers\Dtos;

final class AgentProfileBlockDto extends BaseBlockDto
{
    private const ALLOWED_CONTEXTS = ['default', 'sidebar'];

    private const KNOWN_KEYS = [
        'name', 'title', 'bio', 'phone', 'email', 'license', 'experience',
        'specialties', 'profileImageUrl', 'socialMedia', 'context'
    ];

    public function __construct(
        public string  $name,
        public string  $title,
        public string  $bio,
        public string  $phone,
        public string  $email,
        public string  $license,
        public string  $experience,
        public string  $specialties,
        public string  $languages,
        public ?string $profileImageUrl,
        public array   $socialMedia,
        public string  $context
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'name' => '',
            'title' => '',
            'bio' => '',
            'phone' => '',
            'email' => '',
            'license' => '',
            'experience' => '',
            'specialties' => '',
            'languages' => '',
            'profileImageUrl' => null,
            'socialMedia' => [],
            'context' => 'default'
        ]);

        return new self(
            trim($data['name']),
            trim($data['title']),
            trim($data['bio']),
            trim($data['phone']),
            trim($data['email']),
            trim($data['license']),
            trim($data['experience']),
            trim($data['specialties']),
            trim($data['languages']),
            $data['profileImageUrl'],
            $data['socialMedia'],
            self::validateEnum($data['context'], self::ALLOWED_CONTEXTS, 'default', 'context')
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'bio' => $this->bio,
            'phone' => $this->phone,
            'email' => $this->email,
            'license' => $this->license,
            'experience' => $this->experience,
            'specialties' => $this->specialties,
            'languages' => $this->languages,
            'profileImageUrl' => $this->profileImageUrl,
            'socialMedia' => $this->socialMedia,
            'context' => $this->context,
            'formatted_name' => htmlspecialchars($this->name),
            'formatted_title' => htmlspecialchars($this->title),
            'formatted_bio' => nl2br(htmlspecialchars($this->bio)),
            'has_image' => !empty($this->profileImageUrl),
            'has_social' => !empty($this->socialMedia),
        ];
    }

    public function getType(): string
    {
        return 'agent-profile';
    }
}