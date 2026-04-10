<?php

namespace App\Resources\OpenCollab;

use App\Framework\Resource\JsonResource;

class EarningsResource extends JsonResource
{

    public function toArray(): array
    {
        $totalPence = (int)($this->getAttribute('total_pence') ?? 0);
        $breakdown = $this->getAttribute('breakdown') ?? [];

        return [
            'total_pence' => $totalPence,
            'total_pounds' => number_format($totalPence / 100, 2, '.', ''),
            'breakdown' => array_map(fn(array $item) => [
                'page_id' => $item['page_id'],
                'title' => $item['title'],
                'pence' => (int)$item['total'],
                'pounds' => number_format($item['total'] / 100, 2, '.', ''),
            ], $breakdown),
            'transactions' => $this->getAttribute('transactions') ?? [],
        ];
    }
}