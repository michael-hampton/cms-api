<?php

namespace App\Search;

class PaginatedResult
{
    public function __construct(
        private array $data,
        private int $total,
        private int $page,
        private int $perPage
    ) {}

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getTotalPages(): int
    {
        return (int) ceil($this->total / $this->perPage);
    }

    public function hasMore(): bool
    {
        return $this->page < $this->getTotalPages();
    }

    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'pagination' => [
                'total' => $this->total,
                'per_page' => $this->perPage,
                'current_page' => $this->page,
                'total_pages' => $this->getTotalPages(),
                'has_more' => $this->hasMore()
            ]
        ];
    }
}