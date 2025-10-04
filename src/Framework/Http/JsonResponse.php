<?php

namespace App\Framework\Http;

class JsonResponse extends Response
{
    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        parent::__construct($content, $statusCode, $headers);
    }

    public static function json(array $data, int $statusCode = 200): self
    {
        return new self(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            $statusCode,
            ['Content-Type' => 'application/json']
        );
    }

}