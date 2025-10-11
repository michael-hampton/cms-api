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