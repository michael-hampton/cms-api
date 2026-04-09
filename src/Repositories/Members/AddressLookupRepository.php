<?php

namespace App\Repositories\Members;

use App\Framework\HttpClient\HttpClient;

class AddressLookupRepository
{
    public function __construct(private readonly HttpClient $httpClient)
    {

    }

    public function lookup(string $postcode): array
    {
        // Example using a postcode API
        $response = $this->httpClient->get("https://api.postcodes.io/postcodes/{$postcode}", [
            'verify' => false,
        ]);

        if (!$response->successful()) {
            return [];
        }

        $data = $response->json();

        // Adjust depending on API shape
        return $data['result'] ?? [];
    }
}