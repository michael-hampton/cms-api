<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;
use App\Repositories\Cms\CategoryRepository;

class UpdateCategoryRequest extends FormRequest
{
    private $categoryRepository;

    public function __construct()
    {
        parent::__construct();
        $this->categoryRepository = new CategoryRepository();
    }

    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('update', 'Category');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'string|max:255',
            'description' => 'string|max:1000',
            'color' => 'string|max:7',
            'parent_id' => 'integer|exists:categories,id',
            'sort_order' => 'integer',
            'is_active' => 'boolean'
        ];
    }

    public function after(): array
    {
        return [
            function ($request) {
                if (!empty($request->input('slug'))) {
                    $existing = $this->categoryRepository->findBySlug($request->input('slug'));
                    if ($existing && $existing->id !== $this->route('id')) {
                        throw new ValidationException('Slug already exists');
                    }
                }

                if (!empty($request->input('parent_id'))) {
                    $parent = $this->categoryRepository->find($request->input('parent_id'));
                    if (!$parent) {
                        throw new ValidationException('Parent category not found');
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

        if (!isset($this->data['is_active'])) {
            $this->data['is_active'] = true;
        }

        if (empty($this->data['site_id'])) {
            $this->data['site_id'] = config('app.default_site_id');
        }
    }
}