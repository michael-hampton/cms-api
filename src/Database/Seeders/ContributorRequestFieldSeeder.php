<?php

namespace App\Database\Seeders;

use App\Models\CustomFieldDefinition;
use App\Models\Site;

final class ContributorRequestFieldSeeder
{
    public function run(): void
    {
        foreach (Site::all() as $site) {
            foreach ($this->fields() as $field) {
                $field['site_id'] = $site->id;

                $existing = CustomFieldDefinition::where('context', 'contributor_request')
                    ->where('key', $field['key'])
                    ->where('site_id', $site->id)
                    ->first();

                if ($existing) {
                    $existing->update($field);
                    continue;
                }

                CustomFieldDefinition::create($field);
            }
        }
    }

    private function fields(): array
    {
        return [
            [
                'context' => 'contributor_request',
                'key' => 'name',
                'name' => 'Full name',
                'type' => 'text',
                'render_type' => 'input',
                'description' => '',
                'placeholder' => 'Your full name',
                'is_required' => true,
                'validation_rules' => json_encode(['required', 'string', 'min:2', 'max:120']),
                'options' => json_encode([]),
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'context' => 'contributor_request',
                'key' => 'email',
                'name' => 'Email address',
                'type' => 'email',
                'render_type' => 'input',
                'description' => '',
                'placeholder' => 'you@example.com',
                'is_required' => true,
                'validation_rules' => json_encode(['required', 'email', 'max:255']),
                'options' => json_encode([]),
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'context' => 'contributor_request',
                'key' => 'bio',
                'name' => 'Tell us about yourself',
                'type' => 'textarea',
                'render_type' => 'textarea',
                'description' => 'Minimum 20 characters. This helps us review your request.',
                'placeholder' => "What topics do you write about? What's your background?",
                'is_required' => true,
                'validation_rules' => json_encode(['required', 'string', 'min:20', 'max:1000']),
                'options' => json_encode([]),
                'sort_order' => 30,
                'is_active' => true,
            ],
        ];
    }
}