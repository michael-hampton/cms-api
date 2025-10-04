<?php

namespace App\Framework\Http;

class SafeInput
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get only specified keys from validated data
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->data, array_flip($keys));
    }

    /**
     * Get all except specified keys from validated data
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->data, array_flip($keys));
    }

    /**
     * Get all validated data
     */
    public function all(): array
    {
        return $this->data;
    }
}