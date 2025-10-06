<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;
use App\Repositories\TagRepository;

class CreateTagRequest extends FormRequest
{
    private $tagRepository;

    public function __construct()
    {
        parent::__construct();
        $this->tagRepository = new TagRepository();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'string|max:255',
            'description' => 'string|max:1000',
            'color' => 'string|max:7',
            'is_featured' => 'boolean'
        ];
    }

    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', 'Tag');
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tag name is required',
            'name.max' => 'Tag name cannot exceed 255 characters',
            'slug.max' => 'Slug cannot exceed 255 characters',
            'description.max' => 'Description cannot exceed 1000 characters',
            'color.max' => 'Color value cannot exceed 7 characters'
        ];
    }

    public function after(): array
    {
        return [
            function ($request) {
                if (!empty($request->input('slug'))) {
                    $existing = $this->tagRepository->findBySlug($request->input('slug'));
                    if ($existing) {
                        throw new \App\Framework\Exceptions\ValidationException('Slug already exists');
                    }
                }
            }
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->data['slug']) && !empty($this->data['name'])) {
            $this->data['slug'] = Str::slug($this->data['name']);
        }

        if (empty($this->data['site_id'])) {
            $this->data['site_id'] = config('app.default_site_id');
        }
    }
}