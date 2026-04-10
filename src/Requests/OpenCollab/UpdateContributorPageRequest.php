<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

/**
 * Validates contributor page updates.
 *
 * All fields are optional on update — only provided fields are applied.
 * is_paid/price are validated together: price is required when is_paid is true.
 * status transitions are constrained to what a contributor is allowed to do:
 *   draft → published  (explicit publish action)
 *   published → draft  (unpublish)
 * Contributors cannot set archived, pending, waiting_approval directly.
 */
class UpdateContributorPageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'forms' => ['array'],
            'forms.main' => ['array'],
            'forms.main.title' => ['string', 'max:255'],
            'forms.main.subtitle' => ['string', 'max:500'],
            'forms.main.content' => ['string'],

            'forms.meta' => ['array'],
            'forms.meta.slug' => ['string', 'max:255', 'regex:/^[a-z0-9\-]+$/'],
            'forms.meta.status' => ['string', 'in:draft,published'],
            'forms.meta.publish_date' => ['date'],
            'forms.meta.expiry_date' => ['date', 'after:forms.meta.publish_date'],

            'forms.seo' => ['array'],
            'forms.seo.meta_title' => ['string', 'max:255'],
            'forms.seo.meta_description' => ['string', 'max:500'],

            'is_paid' => ['boolean'],
            'price' => ['integer', 'min:50', 'required_if:is_paid,true'],

            'blocks' => ['array'],
            'blocks.*.type' => ['required_with:blocks', 'string'],
            'blocks.*.data' => ['array'],
            'blocks.*.order' => ['integer', 'min:0'],

            'gallery_slides' => ['array'],
            'gallery_slides.*.image_id' => ['integer'],
            'gallery_slides.*.caption' => ['string', 'max:255'],

            'categories' => ['array'],
            'categories.*' => ['integer'],

            'tags' => ['array'],
            'tags.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'forms.meta.status.in' => 'You can only set the status to draft or published.',
            'forms.meta.slug.regex' => 'Slug may only contain lowercase letters, numbers and hyphens.',
            'price.required_if' => 'A price in pence is required for paid articles.',
            'price.min' => 'The minimum price is 50p.',
        ];
    }
}