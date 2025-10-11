<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class StorePageGridRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:page_grids,slug'],
            'layout' => ['required', 'string', 'in:grid,list,masonry'],
            'columns' => ['required', 'integer', 'min:1', 'max:6'],
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
            'pages.*.image.id' => ['required_with:pages.*.image', 'string'],
            'pages.*.image.url' => ['required_with:pages.*.image', 'string'],
            'pages.*.image.name' => ['required_with:pages.*.image', 'string'],
            'pages.*.image.alt' => ['nullable', 'string'],
            'pages.*.image.caption' => ['nullable', 'string'],
            'pages.*.features' => ['nullable', 'array'],
            'pages.*.features.*' => ['string'],
            'pages.*.actions' => ['nullable', 'array'],
            'pages.*.actions.*.text' => ['required', 'string', 'max:100'],
            'pages.*.actions.*.url' => ['required', 'string', 'max:500'],
            'pages.*.actions.*.style' => ['required', 'string', 'in:primary,secondary,outline'],
            'pages.*.actions.*.target' => ['required', 'string', 'in:_self,_blank'],
            'pages.*.badge' => ['nullable', 'array'],
            'pages.*.badge.text' => ['required_with:pages.*.badge', 'string', 'max:50'],
            'pages.*.badge.color' => ['required_with:pages.*.badge', 'string', 'in:primary,secondary,success,warning,danger,info'],
            'pages.*.badge.background' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'The title field is required.',
            'title.max' => 'The title cannot exceed 255 characters.',
            'subtitle.max' => 'The subtitle cannot exceed 500 characters.',
            'slug.unique' => 'This slug is already in use.',
            'layout.required' => 'The layout field is required.',
            'layout.in' => 'The layout must be one of: grid, list, or masonry.',
            'columns.required' => 'The columns field is required.',
            'columns.min' => 'Columns must be at least 1.',
            'columns.max' => 'Columns cannot exceed 6.',
            'pages.*.title.required' => 'Each page must have a title.',
            'pages.*.slug.required' => 'Each page must have a slug.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'show_excerpt' => $this->boolean('show_excerpt', true),
            'show_image' => $this->boolean('show_image', true),
            'show_features' => $this->boolean('show_features', true),
            'show_actions' => $this->boolean('show_actions', true),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}