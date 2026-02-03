<?php

namespace App\Services\Shared;

class NameParser
{
    public function parse(string $fullName): array
    {
        $fullName = trim($fullName);

        if (empty($fullName)) {
            return [];
        }

        // Split on whitespace
        $parts = preg_split('/\s+/', $fullName);

        if (count($parts) === 1) {
            return [
                'first_name' => $parts[0],
                'last_name' => ''
            ];
        }

        // First word = first name
        // Everything else = last name
        $firstName = array_shift($parts);
        $lastName = implode(' ', $parts);

        return [
            'first_name' => $firstName,
            'last_name' => $lastName
        ];
    }

    public function parseWithMiddle(string $fullName): array
    {
        $fullName = trim($fullName);

        if (empty($fullName)) {
            return [];
        }

        $parts = preg_split('/\s+/', $fullName);

        if (count($parts) === 1) {
            return [
                'first_name' => $parts[0],
                'middle_name' => '',
                'last_name' => ''
            ];
        }

        if (count($parts) === 2) {
            return [
                'first_name' => $parts[0],
                'middle_name' => '',
                'last_name' => $parts[1]
            ];
        }

        // 3+ parts: first, middle(s), last
        $firstName = array_shift($parts);
        $lastName = array_pop($parts);
        $middleName = implode(' ', $parts);

        return [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName
        ];
    }
}