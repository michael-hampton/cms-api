<?php

namespace App\DTO\PublicContent;

/**
 * Top-down newsletter island state. The widget must not invent "subscribed"
 * itself — that fact is resolved server-side for authenticated members only.
 */
final readonly class NewsletterWidgetState
{
    public function __construct(
        public bool $authenticated,
        public bool $subscribed,
        public ?string $loginUrl = null,
        public ?string $manageUrl = null,
        public ?string $newsletterName = null,
        public ?string $newsletterDescription = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'authenticated' => $this->authenticated,
            'subscribed' => $this->subscribed,
            'loginUrl' => $this->loginUrl,
            'manageUrl' => $this->manageUrl,
            'newsletterName' => $this->newsletterName,
            'newsletterDescription' => $this->newsletterDescription,
        ];
    }
}
