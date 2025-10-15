<?php

namespace App\Framework\Resource;

use App\Framework\Http\Response;
use App\Search\PaginatedResult;

class PaginatedResourceCollection
{
    public function __construct(
        private readonly PaginatedResult $result,
        private readonly string          $resourceClass
    ) {}

    public function toArray(): array
    {
        $items = !empty($this->result->getData()) ? collect($this->result->getData())->map(function ($item) {
            return (new $this->resourceClass($item))->toArray();
        })->toArray() : [];

        return [
            'items' => $items,
            'pagination' => [
                'total' => $this->result->getTotal(),
                'per_page' => $this->result->getPerPage(),
                'current_page' => $this->result->getPage(),
                'total_pages' => $this->result->getTotalPages(),
                'has_more' => $this->result->hasMore()
            ]
        ];
    }

    public function toResponse(): Response
    {
        return Response::json($this->toArray());
    }
}