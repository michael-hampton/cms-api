<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;
use App\Repositories\CustomFieldDefinitionRepository;

class CreateCustomFieldDefinitionRequest extends FormRequest
{
    private $customFieldRepository;

    public function __construct()
    {
        parent::__construct();
        $this->customFieldRepository = new CustomFieldDefinitionRepository();
    }

    public function rules(): array
    {
        $allowedTypes = ['text', 'textarea', 'number', 'url', 'email', 'boolean', 'date', 'select', 'multi_select', 'file'];

        return [
            'name' => 'required|string|max:255',
            'key' => 'string|max:255',
            'type' => 'required|in:' . implode(',', $allowedTypes),
            'description' => 'string|max:1000',
            'options' => 'array',
            'validation_rules' => 'array',
            'default_value' => 'string',
            'is_required' => 'boolean',
            'is_searchable' => 'boolean',
            'group_name' => 'string|max:255',
            'sort_order' => 'integer',
            'is_active' => 'boolean'
        ];
    }

//    public function authorize(): bool
//    {
//        return $this->user() && $this->user()->can('create', 'CustomFieldDefinition');
//    }

    public function messages(): array
    {
        return [
            'name.required' => 'Field name is required',
            'type.required' => 'Field type is required',
            'type.in' => 'Invalid field type selected',
            'options.array' => 'Options must be an array'
        ];
    }

    public function after(): array
    {
        return [
            function ($request) {
                if (!empty($request->input('key'))) {
                    $existing = $this->customFieldRepository->findByKey($request->input('key'));
                    if ($existing) {
                        throw new \App\Framework\Exceptions\ValidationException('Field key already exists');
                    }
                }

                // Validate options for select types
                if (in_array($request->input('type'), ['select', 'multi_select'])) {
                    $options = $request->input('options', []);
                    if (empty($options)) {
                        throw new \App\Framework\Exceptions\ValidationException('Options are required for select field types');
                    }
                }
            }
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->data['key']) && !empty($this->data['name'])) {
            $this->data['key'] = $this->generateKey($this->data['name']);
        }

        // Set defaults
        $this->data['is_active'] = $this->data['is_active'] ?? true;
        $this->data['is_required'] = $this->data['is_required'] ?? false;
        $this->data['is_searchable'] = $this->data['is_searchable'] ?? false;
    }

    private function generateKey(string $name): string
    {
        $key = strtolower(trim($name));
        $key = preg_replace('/[^a-z0-9_]/', '_', $key);
        $key = preg_replace('/_+/', '_', $key);
        return trim($key, '_');
    }
}