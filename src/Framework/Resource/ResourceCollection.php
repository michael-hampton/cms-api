<?php

namespace App\Framework\Resource;

use App\Framework\Http\Response;
use App\Framework\Support\Collection;

class ResourceCollection
{
    protected $collection;
    protected $resourceClass;

    public function __construct($collection, string $resourceClass)
    {
        $this->collection = is_array($collection) ? Collection::make($collection) : $collection;
        $this->resourceClass = $resourceClass;
    }

    public function toArray(): array
    {
        return [
            'data' => $this->collection->map(function ($item) {
                return (new $this->resourceClass($item))->toArray();
            })->toArray()
        ];
    }

    public function toResponse(): Response
    {
        return Response::json($this->toArray());
    }
}