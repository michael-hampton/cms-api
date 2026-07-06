<?php

namespace App\Framework\Support\Config;

final class ConfigValidationError
{
    public function __construct(
        public readonly string $entryId,
        public readonly string $key,
        public readonly string $code,
        public readonly string $message,
    ) {
    }

    /**
     * @return array{entryId: string, key: string, code: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'entryId' => $this->entryId,
            'key' => $this->key,
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}