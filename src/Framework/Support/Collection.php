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
        return new static(array_map($callback, $this->items));
    }

    public function filter(?callable $callback = null): self
    {
        if ($callback === null) {
            return new static(array_filter($this->items));
        }

        return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
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

    public function pluck(string $key): self
    {
        return $this->map(function ($item) use ($key) {
            return is_array($item) ? $item[$key] : $item->{$key};
        });
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

    public function sum(?string $key = null)
    {
        if ($key === null) {
            return array_sum($this->items);
        }

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

    public function flatten(int $depth = INF): self
    {
        $flattened = [];

        foreach ($this->items as $item) {
            if (is_array($item) || $item instanceof Collection) {
                $values = $item instanceof Collection ? $item->all() : $item;

                if ($depth === 1) {
                    $flattened = array_merge($flattened, $values);
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

    public function orderBy(string|callable $key, string $direction = 'asc'): Collection
    {
        $direction = strtolower($direction);
        $sorted = $this->items;

        usort($sorted, function ($a, $b) use ($key, $direction) {
            $aValue = is_callable($key)
                ? $key($a)
                : (is_array($a) ? ($a[$key] ?? null) : ($a->$key ?? null));

            $bValue = is_callable($key)
                ? $key($b)
                : (is_array($b) ? ($b[$key] ?? null) : ($b->$key ?? null));

            // Handle nulls (always last)
            if ($aValue === null && $bValue === null) return 0;
            if ($aValue === null) return 1;
            if ($bValue === null) return -1;

            if ($aValue == $bValue) return 0;

            return $direction === 'desc'
                ? ($aValue < $bValue ? 1 : -1)
                : ($aValue < $bValue ? -1 : 1);
        });

        return new static($sorted);
    }


    public function sortByDesc(string|callable $key): Collection
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
            if (is_array($item) && isset($item[$key]) && $item[$key] === $value) {
                return $item;
            } elseif (is_object($item) && isset($item->$key) && $item->$key === $value) {
                return $item;
            } elseif (is_object($item) && method_exists($item, $key) && $item->$key() === $value) {
                return $item;
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

}