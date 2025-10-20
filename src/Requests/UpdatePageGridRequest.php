<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class UpdatePageGridRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pageGridId = $this->route('page_grid');

        return [
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'slug' => ['nullable', 'string', 'max:255'],
            'layout' => ['sometimes', 'string', 'in:grid,list,masonry'],
            'columns' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'show_excerpt' => ['boolean'],
            'show_image' => ['boolean'],
            'show_features' => ['boolean'],
            'show_actions' => ['boolean'],
            'items' => ['nullable', 'array'],
            'items.*.type' => ['required', 'string', 'in:page,author,product'],

            // Page-specific fields
            'items.*.title' => ['required_if:items.*.type,page', 'string', 'max:255'],
            'items.*.slug' => ['required_if:items.*.type,page', 'string', 'max:255'],
            'items.*.excerpt' => ['nullable', 'string'],
            'items.*.url' => ['nullable', 'string', 'max:500'],
            'items.*.location' => ['nullable', 'string', 'max:255'],
            'items.*.price' => ['nullable', 'string', 'max:50'],

            // Author-specific fields
            'items.*.name' => ['required_if:items.*.type,author,product', 'string', 'max:255'],
            'items.*.bio' => ['nullable', 'string'],
            'items.*.author_id' => ['nullable', 'integer'],

            // Product-specific fields
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.description' => ['nullable', 'string'],

            // Common fields for all types
            'items.*.image' => ['nullable', 'array'],
            'items.*.image.id' => ['required_with:items.*.image', 'string'],
            'items.*.image.url' => ['required_with:items.*.image', 'string'],
            'items.*.image.name' => ['required_with:items.*.image', 'string'],
            'items.*.image.alt' => ['nullable', 'string'],
            'items.*.image.caption' => ['nullable', 'string'],
            'items.*.features' => ['nullable', 'array'],
            'items.*.features.*' => ['string'],
            'items.*.actions' => ['nullable', 'array'],
            'items.*.actions.*.text' => ['required', 'string', 'max:100'],
            'items.*.actions.*.url' => ['required', 'string', 'max:500'],
            'items.*.actions.*.style' => ['required', 'string', 'in:primary,secondary,outline'],
            'items.*.actions.*.target' => ['required', 'string', 'in:_self,_blank'],
            'items.*.badge' => ['nullable', 'array'],
            'items.*.badge.text' => ['required_with:items.*.badge', 'string', 'max:50'],
            'items.*.badge.color' => ['required_with:items.*.badge', 'string', 'in:primary,secondary,success,warning,danger,info'],
            'items.*.badge.background' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('show_excerpt')) {
            $this->merge(['show_excerpt' => $this->boolean('show_excerpt')]);
        }
        if ($this->has('show_image')) {
            $this->merge(['show_image' => $this->boolean('show_image')]);
        }
        if ($this->has('show_features')) {
            $this->merge(['show_features' => $this->boolean('show_features')]);
        }
        if ($this->has('show_actions')) {
            $this->merge(['show_actions' => $this->boolean('show_actions')]);
        }
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }
}