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
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('page_grids', 'slug')->ignore($pageGridId)
            ],
            'layout' => ['sometimes', 'string', 'in:grid,list,masonry'],
            'columns' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'show_excerpt' => ['boolean'],
            'show_image' => ['boolean'],
            'show_features' => ['boolean'],
            'show_actions' => ['boolean'],
            'pages' => ['nullable', 'array'],
            'pages.*.title' => ['required', 'string', 'max:255'],
            'pages.*.slug' => ['required', 'string', 'max:255'],
            'pages.*.excerpt' => ['nullable', 'string'],
            'pages.*.url' => ['nullable', 'string', 'max:500'],
            'pages.*.location' => ['nullable', 'string', 'max:255'],
            'pages.*.price' => ['nullable', 'string', 'max:50'],
            'pages.*.image' => ['nullable', 'array'],
            'pages.*.features' => ['nullable', 'array'],
            'pages.*.features.*' => ['string'],
            'pages.*.actions' => ['nullable', 'array'],
            'pages.*.badge' => ['nullable', 'array'],
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