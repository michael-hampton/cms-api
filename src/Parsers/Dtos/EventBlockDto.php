<?php

namespace App\Parsers\Dtos;

final class EventBlockDto extends BaseBlockDto
{
    private const ALLOWED_CONTEXTS = ['default', 'sidebar'];

    private const KNOWN_KEYS = [
        'title', 'description', 'startDate', 'endDate', 'startTime', 'endTime',
        'location', 'address', 'mapUrl', 'ticketPrice', 'currency', 'ticketUrl',
        'capacity', 'organizerName', 'organizerEmail', 'organizerPhone', 'category',
        'image', 'showSignupForm', 'featured', 'context'
    ];

    public function __construct(
        public string $title,
        public string $description,
        public string $startDate,
        public string $endDate,
        public string $startTime,
        public string $endTime,
        public string $location,
        public string $address,
        public string $mapUrl,
        public float  $ticketPrice,
        public string $currency,
        public string $ticketUrl,
        public int    $capacity,
        public string $organizerName,
        public string $organizerEmail,
        public string $organizerPhone,
        public string $category,
        public ?array $image,
        public bool   $showSignupForm,
        public bool   $featured,
        public string $context
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'title' => '',
            'description' => '',
            'startDate' => '',
            'endDate' => '',
            'startTime' => '',
            'endTime' => '',
            'location' => '',
            'address' => '',
            'mapUrl' => '',
            'ticketPrice' => 0.0,
            'currency' => '£',
            'ticketUrl' => '',
            'capacity' => 0,
            'organizerName' => '',
            'organizerEmail' => '',
            'organizerPhone' => '',
            'category' => '',
            'image' => null,
            'showSignupForm' => false,
            'featured' => false,
            'context' => 'default'
        ]);

        $endDate = $data['endDate'] ?: $data['startDate'];

        return new self(
            trim($data['title']),
            trim($data['description']),
            $data['startDate'],
            $endDate,
            $data['startTime'],
            $data['endTime'],
            trim($data['location']),
            trim($data['address']),
            $data['mapUrl'],
            (float)$data['ticketPrice'],
            $data['currency'],
            $data['ticketUrl'],
            (int)$data['capacity'],
            trim($data['organizerName']),
            $data['organizerEmail'],
            $data['organizerPhone'],
            trim($data['category']),
            $data['image'],
            (bool)$data['showSignupForm'],
            (bool)$data['featured'],
            self::validateEnum($data['context'], self::ALLOWED_CONTEXTS, 'default', 'context')
        );
    }

    private function formatDateTime(string $date, string $time = ''): string
    {
        if (empty($date)) return '';

        $dateObj = new \DateTime($date);
        $formatted = $dateObj->format('F j, Y');

        if (!empty($time)) {
            $formatted .= ' at ' . $time;
        }

        return $formatted;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
            'location' => $this->location,
            'address' => $this->address,
            'mapUrl' => $this->mapUrl,
            'ticketPrice' => $this->ticketPrice,
            'currency' => $this->currency,
            'ticketUrl' => $this->ticketUrl,
            'capacity' => $this->capacity,
            'organizerName' => $this->organizerName,
            'organizerEmail' => $this->organizerEmail,
            'organizerPhone' => $this->organizerPhone,
            'category' => $this->category,
            'image' => $this->image,
            'showSignupForm' => $this->showSignupForm,
            'featured' => $this->featured,
            'formatted_description' => nl2br(htmlspecialchars($this->description)),
            'formatted_start_datetime' => $this->formatDateTime($this->startDate, $this->startTime),
            'formatted_end_datetime' => $this->formatDateTime($this->endDate, $this->endTime),
            'is_free' => $this->ticketPrice == 0,
            'is_multi_day' => $this->startDate !== $this->endDate && !empty($this->endDate),
            'has_time' => !empty($this->startTime),
            'context' => $this->context,
        ];
    }

    public function getType(): string
    {
        return 'event';
    }
}