<?php

namespace App\Framework\Support;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

class Collection implements IteratorAggregate, Countable, JsonSerializable
{
    public $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public static function make(array $items = []): self
    {
        return new static($items);
    }

    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function first(?callable $callback = null)
    {
        if ($callback === null) {
            return reset($this->items) ?: null;
        }

        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return null;
    }

    public function last(?callable $callback = null)
    {
        if ($callback === null) {
            return end($this->items) ?: null;
        }

        $items = array_reverse($this->items, true);
        foreach ($items as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return null;
    }

    public function map(callable $callback): self
    {
        $items = [];

        foreach ($this->items as $key => $item) {
            $items[$key] = $callback($item, $key);
        }

        return new static($items);
    }

    public function filter(?callable $callback = null): self
    {
        if ($callback === null) {
            return new static(array_filter($this->items));
        }

        return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    public function values(): self
    {
        return new static(array_values($this->items));
    }

    public function where(string $key, $operator, $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        return $this->filter(function ($item) use ($key, $operator, $value) {
            $itemValue = is_array($item) ? $item[$key] : $item->{$key};

            switch ($operator) {
                case '=':
                case '==':
                    return $itemValue == $value;
                case '===':
                    return $itemValue === $value;
                case '!=':
                    return $itemValue != $value;
                case '>':
                    return $itemValue > $value;
                case '>=':
                    return $itemValue >= $value;
                case '<':
                    return $itemValue < $value;
                case '<=':
                    return $itemValue <= $value;
                default:
                    return false;
            }
        });
    }

    public function pluck(string|array $key, ?string $keyBy = null): self
    {
        // Handle multiple keys
        if (is_array($key)) {
            return $this->map(function ($item) use ($key) {
                $result = [];
                foreach ($key as $k) {
                    $result[$k] = $this->getNestedValue($item, $k);
                }
                return $result;
            });
        }

        // Handle single key with optional key-by
        $results = $this->map(function ($item) use ($key) {
            return $this->getNestedValue($item, $key);
        });

        // If keyBy is provided, reindex the collection
        if ($keyBy !== null) {
            $keyed = [];
            foreach ($results->items as $index => $value) {
                $keyValue = $this->getNestedValue($this->items[$index], $keyBy);
                $keyed[$keyValue] = $value;
            }
            return new self($keyed);
        }

        return $results;
    }

    protected function getNestedValue($item, string $key)
    {
        $segments = explode('.', $key);
        $value = $item;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } elseif (is_object($value)) {
                // Try to access as property first (handles magic __get methods)
                if ($value->{$segment}) {
                    $value = $value->{$segment};
                } // Try as array access (for ArrayAccess objects)
                elseif ($value instanceof \ArrayAccess && isset($value[$segment])) {
                    $value = $value[$segment];
                } // Check if property actually exists
                elseif (property_exists($value, $segment)) {
                    $value = $value->{$segment};
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }

        return $value;
    }

    public function groupBy($key): self
    {
        $groups = [];

        foreach ($this->items as $item) {

            // Determine group key
            if ($key instanceof \Closure) {
                $groupKey = $key($item);
            } else {
                $groupKey = is_array($item) ? $item[$key] : $item->{$key};
            }

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = new static();
            }

            $groups[$groupKey]->push($item);
        }

        return new static($groups);
    }

    public function sortBy(string|callable $key, int $options = SORT_REGULAR, bool $descending = false): self
    {
        $items = $this->items;

        uasort($items, function ($a, $b) use ($key, $options, $descending) {

            $valueA = is_callable($key)
                ? $key($a)
                : (is_array($a) ? ($a[$key] ?? null) : ($a->{$key} ?? null));

            $valueB = is_callable($key)
                ? $key($b)
                : (is_array($b) ? ($b[$key] ?? null) : ($b->{$key} ?? null));

            $result = $valueA <=> $valueB;

            return $descending ? -$result : $result;
        });

        return new static($items);
    }

    public function push($item): self
    {
        $this->items[] = $item;
        return $this;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function toArray(): array
    {
        return array_map(function ($item) {
            if ($item instanceof Model) {
                return $item->toArray();
            }
            if ($item instanceof Collection) {
                return $item->toArray();
            }
            if (is_object($item) && method_exists($item, 'toArray')) {
                return $item->toArray();
            }
            return $item;
        }, $this->items);
    }

    public function toJson(): string
    {
        return json_encode($this->jsonSerialize());
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function each(callable $callback): self
    {
        foreach ($this->items as $key => $item) {
            $callback($item, $key);
        }

        return $this;
    }

    public function reject(callable $callback): self
    {
        return $this->filter(function ($item, $key) use ($callback) {
            return !$callback($item, $key);
        });
    }

    public function unique(?string $key = null): self
    {
        if ($key === null) {
            return new static(array_unique($this->items, SORT_REGULAR));
        }

        $unique = [];
        $usedKeys = [];

        foreach ($this->items as $item) {
            $value = is_array($item) ? $item[$key] : $item->{$key};

            if (!in_array($value, $usedKeys, true)) {
                $unique[] = $item;
                $usedKeys[] = $value;
            }
        }

        return new static($unique);
    }

    public function chunk(int $size): self
    {
        $chunks = [];

        for ($i = 0; $i < count($this->items); $i += $size) {
            $chunks[] = new static(array_slice($this->items, $i, $size));
        }

        return new static($chunks);
    }

    public function take(int $limit): self
    {
        if ($limit < 0) {
            return new static(array_slice($this->items, $limit));
        }

        return new static(array_slice($this->items, 0, $limit));
    }

    public function skip(int $offset): self
    {
        return new static(array_slice($this->items, $offset));
    }

    public function merge($items): self
    {
        if ($items instanceof Collection) {
            $items = $items->all();
        }

        return new static(array_merge($this->items, $items));
    }

    public function sum(string|callable|null $key = null): float|int
    {
        // Case 1: no key → sum values directly
        if ($key === null) {
            return array_sum($this->items);
        }

        // Case 2: callable → map then sum
        if (is_callable($key)) {
            return array_sum(
                array_map($key, $this->items)
            );
        }

        // Case 3: string key → pluck then sum
        return $this->pluck($key)->sum();
    }

    public function avg(?string $key = null)
    {
        $count = $this->count();

        if ($count === 0) {
            return 0;
        }

        return $this->sum($key) / $count;
    }

    public function min(?string $key = null)
    {
        if ($key === null) {
            return min($this->items);
        }

        return $this->pluck($key)->min();
    }

    public function max(?string $key = null)
    {
        if ($key === null) {
            return max($this->items);
        }

        return $this->pluck($key)->max();
    }

    public function contains($key, $operator = null, $value = null): bool
    {
        if ($key instanceof \Closure) {
            foreach ($this->items as $item) {
                if ($key($item)) {
                    return true;
                }
            }

            return false;
        }

        if (func_num_args() === 1) {
            return in_array($key, $this->items, true);
        }

        return $this->where($key, $operator, $value)->isNotEmpty();
    }

    public function keyBy($key): self
    {
        $keyed = [];

        foreach ($this->items as $item) {
            $keyValue = is_callable($key) ? $key($item) : (is_array($item) ? $item[$key] : $item->{$key});
            $keyed[$keyValue] = $item;
        }

        return new static($keyed);
    }

    public function whereNotNull(string $key): self
    {
        $filtered = array_filter($this->items, function ($item) use ($key) {
            if (is_array($item)) {
                return isset($item[$key]) && $item[$key] !== null;
            } elseif (is_object($item)) {
                // Use getAttribute if model implements it, fallback to property access
                if (method_exists($item, 'getAttribute')) {
                    return $item->getAttribute($key) !== null;
                }
                return isset($item->{$key}) && $item->{$key} !== null;
            }
            return false;
        });

        return new static($filtered);
    }

    public function whereNull(string $key): self
    {
        $filtered = array_filter($this->items, function ($item) use ($key) {
            if (is_array($item)) {
                return array_key_exists($key, $item) && $item[$key] === null;
            } elseif (is_object($item)) {
                // Use getAttribute if model implements it, fallback to property access
                if (method_exists($item, 'getAttribute')) {
                    return $item->getAttribute($key) === null;
                }
                return property_exists($item, $key) && $item->{$key} === null;
            }
            return false;
        });

        return new static($filtered);
    }

    public function whereNotEmpty(string $key): self
    {
        $filtered = array_filter($this->items, function ($item) use ($key) {
            if (is_array($item)) {
                return !empty($item[$key]);
            } elseif (is_object($item)) {
                // Use getAttribute if available, fallback to property
                if (method_exists($item, 'getAttribute')) {
                    return !empty($item->getAttribute($key));
                }
                return !empty($item->{$key});
            }
            return false;
        });

        return new static($filtered);
    }


    public function flatten($depth = INF): self
    {
        $flattened = [];

        foreach ($this->items as $item) {
            if (is_array($item) || $item instanceof Collection) {
                $values = $item instanceof Collection ? $item->all() : $item;

                if ($depth === 1) {
                    $flattened = array_merge($flattened, $values);
                } elseif ($depth === INF || $depth === PHP_FLOAT_MAX) {
                    $flattened = array_merge($flattened, (new static($values))->flatten($depth)->all());
                } else {
                    $flattened = array_merge($flattened, (new static($values))->flatten($depth - 1)->all());
                }
            } else {
                $flattened[] = $item;
            }
        }

        return new static($flattened);
    }

    public function collapse(): self
    {
        return $this->flatten(1);
    }

    public function zip($items): self
    {
        $zipped = [];
        $items = $items instanceof Collection ? $items->all() : $items;

        foreach ($this->items as $index => $item) {
            $zipped[] = [$item, $items[$index] ?? null];
        }

        return new static($zipped);
    }

    public function orderBy(string|callable|null $key = null, string $direction = 'asc'): Collection
    {
        $direction = strtolower($direction);
        $sorted = $this->items;

        usort($sorted, function ($a, $b) use ($key, $direction) {
            // Determine value to compare
            $getValue = fn($item) => match (true) {
                $key === null => $item, // sort by the value itself
                is_callable($key) => $key($item),
                is_array($item) => $item[$key] ?? null,
                default => $item->$key ?? null,
            };

            $aValue = $getValue($a);
            $bValue = $getValue($b);

            // Handle nulls: always last
            if ($aValue === null && $bValue === null) return 0;
            if ($aValue === null) return 1;
            if ($bValue === null) return -1;

            // Compare numerically if possible
            if (is_numeric($aValue) && is_numeric($bValue)) {
                $cmp = $aValue <=> $bValue;
            } else {
                $cmp = strcmp((string)$aValue, (string)$bValue);
            }

            return $direction === 'desc' ? -$cmp : $cmp;
        });

        return new static($sorted);
    }


    public function sortByDesc(string|callable|null $key = null): Collection
    {
        return $this->orderBy($key, 'desc');
    }


    /**
     * Return the first item that matches a given key/value or callback
     *
     * Usage:
     *   $collection->firstWhere('status', 'active');
     *   $collection->firstWhere(fn($item) => $item->isActive());
     *
     * @param string|callable $key
     * @param mixed $value
     * @return mixed|null
     */
    public function firstWhere($key, $value = null)
    {
        if (is_callable($key)) {
            foreach ($this->items as $item) {
                if ($key($item)) {
                    return $item;
                }
            }
            return null;
        }

        foreach ($this->items as $item) {
            if (is_array($item) && array_key_exists($key, $item) && $item[$key] === $value) {
                return $item;
            }

            if (is_object($item)) {
                // Use magic getter for Eloquent models / objects
                if (isset($item->$key) && $item->$key === $value) {
                    return $item;
                }

                // Optional: use getAttribute if Eloquent
                if (method_exists($item, 'getAttribute') && $item->getAttribute($key) === $value) {
                    return $item;
                }
            }
        }

        return null;
    }

    public function get(mixed $index)
    {
        if (!array_key_exists($index, $this->items)) { // <-- array_key_exists supports string keys
            return collect();
        }

        return $this->items[$index];
    }

    public function keys()
    {
        return array_keys($this->items);
    }

    public function has(mixed $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function mapWithKeys(callable $callback)
    {
        $result = [];

        foreach ($this->items as $key => $value) {
            $assoc = $callback($value, $key);

            if (!is_array($assoc)) {
                throw new \UnexpectedValueException("Callback must return an associative array.");
            }

            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return new static($result);
    }

    /**
     * Return a slice of the collection.
     *
     * @param int $offset The starting index.
     * @param int|null $length The number of items to return (null = until end).
     * @return static           A new collection instance.
     */
    public function slice(int $offset, ?int $length = null): static
    {
        if ($length === null) {
            $sliced = array_slice($this->items, $offset);
        } else {
            $sliced = array_slice($this->items, $offset, $length);
        }

        return new static($sliced);
    }

    public function concat(iterable $items): self
    {
        $merged = [];

        foreach ($this->items as $item) {
            $merged[] = $item;
        }

        foreach ($items as $item) {
            $merged[] = $item;
        }

        return new self($merged);
    }

    public function whereIn(string $key, array $values): self
    {
        $filtered = array_filter($this->items, function ($item) use ($key, $values) {

            // If item is an object with attribute array (like Eloquent model)
            if (is_object($item)) {
                // Try property directly
                if (isset($item->$key)) {
                    return in_array($item->$key, $values, true);
                }

                // Try attribute bag (Eloquent-style)
                if (method_exists($item, 'getAttribute')) {
                    $attr = $item->getAttribute($key);
                    return in_array($attr, $values, true);
                }
            }

            // Array support (if needed)
            if (is_array($item) && array_key_exists($key, $item)) {
                return in_array($item[$key], $values, true);
            }

            return false;
        });

        return new self(array_values($filtered));
    }

    public function reduce(callable $callback, $initial = null)
    {
        $carry = $initial;

        foreach ($this->items as $key => $item) {
            $carry = $callback($carry, $item, $key);
        }

        return $carry;
    }

    public function sort(): static
    {
        $items = $this->items;

        sort($items);

        return new static($items);
    }

}