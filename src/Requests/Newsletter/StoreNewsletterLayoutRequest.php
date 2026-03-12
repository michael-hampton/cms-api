<?php

namespace App\Requests\Newsletter;

use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;

class StoreNewsletterLayoutRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['string', 'max:255', 'required'],
            'site_id' => ['integer'],
            'created_by' => ['integer'],
            'layout_definition' => ['array'],

            // Top-level layout settings
            'layout_definition.schema_version' => ['integer'],
            'layout_definition.columns' => ['integer', 'min:1'],
            'layout_definition.content_style' => ['string'],
            'layout_definition.header_style' => ['string'],
            'layout_definition.max_articles' => ['integer', 'min:1'],
            'layout_definition.articles_per_row' => ['integer', 'min:1'],
            'layout_definition.show_images' => ['boolean'],
            'layout_definition.show_excerpts' => ['boolean'],
            'layout_definition.show_author' => ['boolean'],
            'layout_definition.show_date' => ['boolean'],
            'layout_definition.featured_first' => ['boolean'],

            // Regions
            'layout_definition.regions' => ['array'],
            'layout_definition.regions.*.id' => ['required', 'string'],
            'layout_definition.regions.*.name' => ['required', 'string'],
            'layout_definition.regions.*.order' => ['integer'],
            'layout_definition.regions.*.slots' => ['array'],

            // Slots within regions
            'layout_definition.regions.*.slots.*.name' => ['string'],
            'layout_definition.regions.*.slots.*.blocks' => ['array'],

            // Blocks within slots
            'layout_definition.regions.*.slots.*.blocks.*.type' => ['required', 'string'],
            'layout_definition.regions.*.slots.*.blocks.*.data' => ['array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->data['slug']) && !empty($this->data['name'])) {
            $this->data['slug'] = Str::slug($this->data['name']);
        }
    }
}