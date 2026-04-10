<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

/**
 * Contributor pages use the same nested forms.* structure as admin pages.
 * We add contributor-specific fields on top: is_paid and price.
 *
 * contributor_id is NOT accepted from the request — it is injected by
 * ContributorPageService from the authenticated user. Never trust client input for ownership.
 */
class StoreContributorPageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer'],

            'forms' => ['array'],
            'forms.main' => ['array'],
            'forms.main.title' => ['required', 'string', 'max:255'],
            'forms.main.subtitle' => ['string'],

            'forms.meta' => ['array'],
            'forms.meta.slug' => ['string', 'max:255'],
            'forms.meta.status' => ['string'],
            'forms.meta.publish_date' => ['date'],
            'forms.meta.expiry_date' => ['date'],

            'forms.seo' => ['array'],
            'forms.seo.meta_title' => ['string', 'max:255'],
            'forms.seo.meta_description' => ['string'],

            'is_paid' => ['boolean'],
            //'price'   => ['required_if:is_paid,true', 'integer', 'min:1'],

            'blocks' => ['array'],
            'gallery_slides' => ['array'],
        ];
    }
}