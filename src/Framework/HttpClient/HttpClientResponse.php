<?php

namespace App\Framework\HttpClient;

class HttpClientResponse
{
    public function getStatusCode()
    {
        return 200;
    }

    public function getBody()
    {
        return "test";
    }
}