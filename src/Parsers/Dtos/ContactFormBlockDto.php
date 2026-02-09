<?php

namespace App\Parsers\Dtos;

final class ContactFormBlockDto extends BaseBlockDto
{
    private const ALLOWED_CONTEXTS = ['default', 'sidebar'];

    private const KNOWN_KEYS = [
        'title', 'subtitle', 'showName', 'showEmail', 'showPhone', 'showSubject',
        'showMessage', 'showPropertyInterest', 'submitButtonText', 'successMessage',
        'recipientEmail', 'requireName', 'requireEmail', 'requirePhone', 'requireSubject',
        'requireMessage', 'override_email', 'override_phone', 'override_address',
        'override_social', 'context'
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
        public string  $context
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
            self::validateEnum($data['context'], self::ALLOWED_CONTEXTS, 'default', 'context')
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
                'email' => $this->override_email,
                'phone' => $this->override_phone,
                'address' => $this->override_address,
                'social' => $this->override_social
            ]
        ];
    }

    public function getType(): string
    {
        return 'contact-form';
    }
}