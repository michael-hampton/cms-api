<?php

namespace App\DTO\Newsletters;

/**
 * Carries validated data for a manual issue send request.
 */
final class IssueManualSendDTO
{
    private const VALID_SEND_TYPES = ['all', 'custom'];

    private function __construct(
        public readonly string $sendType,
        public readonly array  $customEmails,
    )
    {
    }

    /**
     * @throws \InvalidArgumentException on validation failure
     */
    public static function fromArray(array $data): self
    {
        $sendType = $data['send_type'] ?? 'all';

        if (!in_array($sendType, self::VALID_SEND_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Invalid send_type '{$sendType}'. Must be 'all' or 'custom'."
            );
        }

        $customEmails = [];

        if ($sendType === 'custom') {
            $raw = $data['custom_emails'] ?? [];

            if (!is_array($raw)) {
                throw new \InvalidArgumentException('custom_emails must be an array.');
            }

            $customEmails = array_values(array_filter(
                array_map('trim', $raw),
                static fn(string $e): bool => filter_var($e, FILTER_VALIDATE_EMAIL) !== false
            ));

            if (empty($customEmails)) {
                throw new \InvalidArgumentException(
                    'At least one valid email address is required for custom sends.'
                );
            }
        }

        return new self($sendType, $customEmails);
    }

    public function isCustom(): bool
    {
        return $this->sendType === 'custom';
    }
}