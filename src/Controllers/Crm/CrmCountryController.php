<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Models\Country;

class CrmCountryController extends Controller
{
    public function index(string $site): JsonResponse
    {

        return $this->resourceResponse(['countries' =>
            Country::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Country $country) => [
                    'code' => $country->code,
                    'name' => $country->name,
                ])
                ->values()
                ->all()
        ]);
    }
}