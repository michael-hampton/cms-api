<?php

namespace App\Parsers\Dtos;

final class EventSignupBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = [
        'title'];

    public function __construct(
        public ?int   $eventId,
        public string $title,
        public string $description,
        public string $date,
        public string $time,
        public string $location,
        public int    $capacity,
        public int    $registeredCount,
        public string $registrationUrl,
        public float  $price,
        public string $currency,
        public bool   $isFree,
        public bool   $showSignupForm,
        public bool   $requireName,
        public bool   $requireEmail,
        public bool   $requirePhone,
        public bool   $requireCompany,
        public bool   $autoConfirmation,
        public bool   $trackCapacity,
        public int    $maxSignups,
        public bool   $showName,
        public bool   $showEmail,
        public bool   $showPhone,
        public bool   $showCompany,
        public bool   $showDietaryReqs,
        public bool   $showAccessibilityReqs,
        public string $submitButtonText
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'event_id' => null,
            'title' => '',
            'description' => '',
            'date' => '',
            'time' => '',
            'location' => '',
            'capacity' => 0,
            'registeredCount' => 0,
            'registrationUrl' => '',
            'price' => 0.0,
            'currency' => '$',
            'isFree' => true,
            'showSignupForm' => false,
            'requireName' => false,
            'requireEmail' => false,
            'requirePhone' => false,
            'requireCompany' => false,
            'autoConfirmation' => false,
            'trackCapacity' => 0,
            'maxSignups' => 0,
            'showName' => false,
            'showEmail' => false,
            'showPhone' => false,
            'showCompany' => false,
            'showDietaryReqs' => false,
            'showAccessibilityReqs' => false,
            'submitButtonText' => 'Register Now',
        ]);

        return new self(
            $data['event_id'],
            trim($data['title']),
            trim($data['description']),
            trim($data['date']),
            trim($data['time']),
            trim($data['location']),
            (int)$data['capacity'],
            (int)$data['registeredCount'],
            trim($data['registrationUrl']),
            (float)$data['price'],
            $data['currency'],
            (bool)$data['isFree'],
            (bool)$data['showSignupForm'],
            (bool)$data['requireName'] ?? false,
            (bool)$data['requireEmail'] ?? false,
            (bool)$data['requirePhone'] ?? false,
            (bool)$data['requireCompany'] ?? false,
            (bool)$data['autoConfirmation'] ?? false,
            (bool)$data['trackCapacity'] ?? false,
            (int)$data['maxSignups'],
            (bool)$data['showName'],
            (bool)$data['showEmail'],
            (bool)$data['showPhone'],
            (bool)$data['showCompany'],
            (bool)$data['showDietaryReqs'] ?? false,
            (bool)$data['showAccessibilityReqs'] ?? false,
            (string)$data['submitButtonText'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'title' => $this->title,
            'description' => $this->description,
            'date' => $this->date,
            'time' => $this->time,
            'location' => $this->location,
            'capacity' => $this->capacity,
            'registeredCount' => $this->registeredCount,
            'registrationUrl' => $this->registrationUrl,
            'price' => $this->price,
            'currency' => $this->currency,
            'isFree' => $this->isFree,
            'showSignupForm' => $this->showSignupForm,
            'spots_remaining' => $this->spotsRemaining(),
            'is_full' => $this->isFull(),
            'formatted_price' => $this->isFree ? 'Free' : $this->currency . number_format($this->price, 2),
            'requireName' => $this->requireName,
            'requireEmail' => $this->requireEmail,
            'requirePhone' => $this->requirePhone,
            'requireCompany' => $this->requireCompany,
            'autoConfirmation' => $this->autoConfirmation,
            'trackCapacity' => $this->trackCapacity,
            'maxSignups' => $this->maxSignups,
            'showName' => $this->showName,
            'showEmail' => $this->showEmail,
            'showPhone' => $this->showPhone,
            'showCompany' => $this->showCompany,
            'showDietaryReqs' => $this->showDietaryReqs,
            'showAccessibilityReqs' => $this->showAccessibilityReqs,
            'submitButtonText' => $this->submitButtonText,
        ];
    }

    public function spotsRemaining(): int
    {
        return max(0, $this->capacity - $this->registeredCount);
    }

    public function isFull(): bool
    {
        return $this->capacity > 0 && $this->registeredCount >= $this->capacity;
    }

    public function getType(): string
    {
        return 'event-signup';
    }
}