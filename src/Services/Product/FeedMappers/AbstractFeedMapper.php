<?php

namespace App\Services\Product\FeedMappers;

abstract class AbstractFeedMapper implements FeedMapperInterface
{
    /**
     * Get float value
     *
     * @param array $data
     * @param array $keys
     * @param float|null $default
     * @return float|null
     */
    protected function getFloat(array $data, array $keys, ?float $default = null): ?float
    {
        $value = $this->getValue($data, $keys, $default);
        return $value !== null ? (float)$value : null;
    }

    /**
     * Get value from array with fallback keys
     *
     * @param array $data
     * @param array $keys
     * @param mixed $default
     * @return mixed
     */
    protected function getValue(array $data, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                return $data[$key];
            }
        }

        return $default;
    }

    /**
     * Get boolean value
     *
     * @param array $data
     * @param array $keys
     * @param bool $default
     * @return bool
     */
    protected function getBool(array $data, array $keys, bool $default = true): bool
    {
        $value = $this->getValue($data, $keys, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'available', 'in stock']);
        }

        return (bool)$value;
    }

    /**
     * Get string value
     *
     * @param array $data
     * @param array $keys
     * @param string $default
     * @return string
     */
    protected function getString(array $data, array $keys, string $default = ''): string
    {
        return (string)$this->getValue($data, $keys, $default);
    }

    protected function getInteger(array $data, array $keys, int $default = 0): int
    {
        return (int)$this->getValue($data, $keys, $default);
    }
}