<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class StorePageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => ['integer'],
            'status' => ['string'],
            'requires_approval' => ['boolean'],
            'site_id' => ['required', 'integer'],

            'forms' => ['array'],
            'forms.main' => ['array'],
            'forms.main.title' => ['required_without:id', 'string', 'max:255'],
            'forms.main.subtitle' => ['string'],
            'forms.main.owner' => ['integer'],

            'forms.meta' => ['array'],
            'forms.meta.slug' => ['string', 'max:255'],
            'forms.meta.status' => ['string'],
            'forms.meta.content_type' => ['string'],
            'forms.meta.authors' => ['array'],
            'forms.meta.contributors' => ['array'],
            'forms.meta.region_sets' => ['array'],
            'forms.meta.territories' => ['array'],
            'forms.meta.publish_date' => ['date'],
            'forms.meta.expiry_date' => ['date'],
            'forms.meta.visibility' => ['string'],
            'forms.meta.password' => ['string'],
            'forms.meta.featured' => ['boolean'],
            'forms.meta.allow_comments' => ['boolean'],

            'forms.seo' => ['array'],
            'forms.seo.meta_title' => ['string', 'max:255'],
            'forms.seo.meta_description' => ['string'],
            'forms.seo.meta_keywords' => ['string'],
            'forms.seo.canonical_url' => ['url'],
            'forms.seo.no_index' => ['boolean'],
            'forms.seo.no_follow' => ['boolean'],
            'forms.seo.og_title' => ['string', 'max:255'],
            'forms.seo.og_description' => ['string'],
            'forms.seo.og_image' => ['string'],
            'forms.seo.twitter_card' => ['string'],
            'forms.seo.schema_markup' => ['string'],

            'forms.settings' => ['array'],
            'forms.settings.template' => ['string'],
            'forms.settings.custom_css' => ['string'],
            'forms.settings.custom_js' => ['string'],
            'forms.settings.redirect_url' => ['url'],
            'forms.settings.menu_order' => ['integer'],
            'forms.settings.parent_page' => ['integer'],
            'forms.settings.latitude' => ['numeric'],
            'forms.settings.longitude' => ['numeric'],
            'forms.settings.address' => ['string'],
            'forms.settings.price' => ['numeric'],
            'forms.settings.currency' => ['string', 'max:3'],
            'forms.settings.sale_price' => ['numeric'],
            'forms.settings.recurring' => ['boolean'],
            'forms.settings.recurring_period' => ['string'],
            'forms.settings.access_roles' => ['array'],

            'forms.social' => ['array'],
            'forms.social.enable_sharing' => ['boolean'],
            'forms.social.platforms' => ['array'],
            'forms.social.share_text' => ['string'],
            'forms.social.share_hashtags' => ['string'],

            'forms.tags' => ['array'],
            'forms.tags.categories' => ['array'],
            'forms.tags.tags' => ['array'],
            'forms.tags.products' => ['array'],
            'forms.tags.customFields' => ['array'],

            'forms.listing' => ['array'],
            'forms.listing.synopsis' => ['string'],
            'forms.listing.listingTitle' => ['string', 'max:255'],
            'forms.listing.dekLabel' => ['string'],
            'forms.listing.imageId' => ['integer'],
            'forms.listing.useAsHero' => ['boolean'],

            'blocks' => ['array'],
            'gallery_slides' => ['array'],
            'resolved_images' => ['array'],
            'forms.cropOverrides' => ['array'],
            'zones' => ['array'],

            'hero_type' => ['string'],
            'hero_image_id' => ['integer'],
            'hero_video_url' => ['url'],
        ];
    }
}