<?php

namespace App\Framework\HttpClient;

class HttpClient implements HttpClientInterface
{

    public function get(mixed $feedUrl, array $data)
    {
        return new HttpClientResponse();
    }
}