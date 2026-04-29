<?php

namespace App\Framework\Database;

use App\Framework\Support\Collection;

class CursorPaginator
{
    public function __construct(
        private ?int $lastId = null
    )
    {
    }

    public static function from(?string $cursor): self
    {
        if (!$cursor) {
            return new self(null);
        }

        $decoded = json_decode(base64_decode($cursor), true);

        return new self($decoded['id'] ?? null);
    }

    public function apply(QueryBuilder $query): QueryBuilder
    {
        if ($this->lastId === null) {
            return $query;
        }

        return $query->where('id', '<', $this->lastId);
    }

    public function paginate(Collection $items, int $perPage): array
    {
        $hasMore = $items->count() > $perPage;

        $items = $hasMore
            ? $items->take($perPage)
            : $items;

        return [
            'items' => $items->values()->all(),
            'next_cursor' => $hasMore
                ? $this->encode($items->last()->id)
                : null,
        ];
    }

    private function encode(int $id): string
    {
        return base64_encode(json_encode([
            'id' => $id,
        ]));
    }
}