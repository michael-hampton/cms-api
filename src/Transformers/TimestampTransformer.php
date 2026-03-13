<?php

namespace App\Transformers;

class TimestampTransformer
{
    public static function transform(array $item): array
    {
        return [
            ...$item,
            'created_at' => $item['created_at']?->format('Y-m-d H:i:s'),
            'updated_at' => $item['updated_at']?->format('Y-m-d H:i:s'),
        ];
    }
}