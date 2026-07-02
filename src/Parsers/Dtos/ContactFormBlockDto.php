<?php

namespace App\Parsers\Dtos;

use App\Framework\Support\SiteContext;

final class ContactFormBlockDto extends BaseBlockDto
{
    private const ALLOWED_CONTEXTS = ['default', 'sidebar'];

    private const KNOWN_KEYS = [
        'title', 'showName', 'showEmail', 'showPhone', 'showSubject',
        'showMessage', 'submitButtonText', 'requireName'
    ];

    public function __construct(
        public string  $title,
        public string  $subtitle,
        public bool    $showName,
        public bool    $showEmail,
        public bool    $showPhone,
        public bool    $showSubject,
        public bool    $showMessage,
        public bool    $showPropertyInterest,
        public string  $submitButtonText,
        public string  $successMessage,
        public string  $recipientEmail,
        public bool    $requireName,
        public bool    $requireEmail,
        public bool    $requirePhone,
        public bool    $requireSubject,
        public bool    $requireMessage,
        public ?string $override_email,
        public ?string $override_phone,
        public ?array  $override_address,
        public ?array  $override_social,
        public string $context,
        public array  $contactInfo = []
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'title' => '',
            'subtitle' => '',
            'showName' => true,
            'showEmail' => true,
            'showPhone' => false,
            'showSubject' => false,
            'showMessage' => true,
            'showPropertyInterest' => false,
            'submitButtonText' => 'Send',
            'successMessage' => 'Message sent successfully!',
            'recipientEmail' => '',
            'requireName' => true,
            'requireEmail' => true,
            'requirePhone' => false,
            'requireSubject' => false,
            'requireMessage' => true,
            'override_email' => null,
            'override_phone' => null,
            'override_address' => null,
            'override_social' => null,
            'context' => 'default'
        ]);

        $site = SiteContext::get();
        $contactInfo = $site ? $site->getContactInfo() : self::getDefaultContactInfo();

        return new self(
            trim($data['title']),
            trim($data['subtitle']),
            (bool)$data['showName'],
            (bool)$data['showEmail'],
            (bool)$data['showPhone'],
            (bool)$data['showSubject'],
            (bool)$data['showMessage'],
            (bool)$data['showPropertyInterest'],
            $data['submitButtonText'],
            $data['successMessage'],
            $data['recipientEmail'],
            (bool)$data['requireName'],
            (bool)$data['requireEmail'],
            (bool)$data['requirePhone'],
            (bool)$data['requireSubject'],
            (bool)$data['requireMessage'],
            $data['override_email'],
            $data['override_phone'],
            $data['override_address'],
            $data['override_social'],
            self::validateEnum($data['context'], self::ALLOWED_CONTEXTS, 'default', 'context'),
            [
                'email' => $data['override_email'] ?? $data['contact_info']['email'] ?? $contactInfo['email'],
                'phone' => $data['override_phone'] ?? $data['contact_info']['phone'] ?? $contactInfo['phone'],
                'address' => $data['override_address'] ?? $data['contact_info']['address'] ?? $contactInfo['address'],
                'social' => $data['override_social'] ?? $data['contact_info']['social'] ?? $contactInfo['social']
            ]

        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'showName' => $this->showName,
            'showEmail' => $this->showEmail,
            'showPhone' => $this->showPhone,
            'showSubject' => $this->showSubject,
            'showMessage' => $this->showMessage,
            'showPropertyInterest' => $this->showPropertyInterest,
            'submitButtonText' => $this->submitButtonText,
            'successMessage' => $this->successMessage,
            'recipientEmail' => $this->recipientEmail,
            'requireName' => $this->requireName,
            'requireEmail' => $this->requireEmail,
            'requirePhone' => $this->requirePhone,
            'requireSubject' => $this->requireSubject,
            'requireMessage' => $this->requireMessage,
            'formatted_title' => htmlspecialchars($this->title),
            'formatted_subtitle' => htmlspecialchars($this->subtitle),
            'context' => $this->context,
            'contact_info' => [
                'email' => $this->contactInfo['email'],
                'phone' => $this->contactInfo['phone'],
                'address' => $this->contactInfo['address'],
                'social' => $this->contactInfo['social']
            ]
        ];
    }

    public function getType(): string
    {
        return 'contact-form';
    }

    private static function getDefaultContactInfo(): array
    {
        return [
            'email' => 'hello@example.com',
            'phone' => '+44 20 7123 4567',
            'address' => [
                'line1' => '123 Example Street',
                'line2' => '',
                'city' => 'London',
                'postcode' => 'SW1A 1AA',
                'country' => 'UK'
            ],
            'social' => [
                'facebook' => '#',
                'instagram' => '#',
                'twitter' => '#',
                'linkedin' => '#'
            ]
        ];
    }
}