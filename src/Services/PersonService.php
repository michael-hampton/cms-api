<?php

namespace App\Services;

use App\Framework\Database\Database;
use App\Models\Person;

class PersonService
{
    public function __construct(private Database $database) {}

    public function createOrUpdatePerson(array $personData): ?Person
    {
        // Only save if email is provided (unique identifier)
        if (empty($personData['email'])) {
            return null;
        }

        return Person::updateOrCreate(
            ['email' => $personData['email']],
            [
                'name' => $personData['name'] ?? '',
                'role' => $personData['role'] ?? '',
                'phone' => $personData['phone'] ?? '',
                'bio' => $personData['bio'] ?? '',
                'image' => $personData['image'] ?? null
            ]
        );
    }

    public function getPersonsForSelect(): array
    {
        return Person::all()->map(function($person) {
            return [
                'id' => $person->id,
                'label' => $person->name . ' (' . $person->role . ')'
            ];
        })->toArray();
    }
}