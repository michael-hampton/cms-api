<?php

namespace App\Framework\Http;

class StreamedResponse extends Response
{
    private $callback;

    public function __construct(callable $callback, int $statusCode = 200, array $headers = [])
    {
        parent::__construct('', $statusCode, $headers);
        $this->callback = $callback;
    }

    public function send(): void
    {
        http_response_code($this->getStatusCode());

        foreach ($this->getHeaders() as $name => $value) {
            header("{$name}: {$value}");
        }

        ($this->callback)();
    }
}