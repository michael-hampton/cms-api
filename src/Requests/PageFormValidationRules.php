<?php

namespace App\Requests;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\DateRule;
use App\Framework\Validation\Rules\InRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MinLengthRule;
use App\Framework\Validation\Rules\NumericRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;

class PageFormValidationRules
{
    public function getMainFormRules(): array
    {
        return [
            'title' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
//            'subtitle' => [
//                new MaxLengthRule(500)
//            ]
        ];
    }

    public function getMetaFormRules(): array
    {
        return [
//            'title' => [
//                new RequiredRule(),
//                new MaxLengthRule(255)
//            ],
//            'slug' => [
//                new RequiredRule(),
//                new MaxLengthRule(255),
//                new MinLengthRule(1)
//            ],
//            'description' => [
//                new MaxLengthRule(1000)
//            ],
//            'contentType' => [
//                new MaxLengthRule(100)
//            ],
//            'blockCategory' => [
//                new MaxLengthRule(100)
//            ],
//            'author' => [
//                new MaxLengthRule(255)
//            ],
//            'publishDate' => [
//                new DateRule()
//            ],
//            'expiryDate' => [
//                new DateRule()
//            ],
//            'status' => [
//                new RequiredRule(),
//                new InRule(['Draft', 'Published', 'Archived'])
//            ],
//            'visibility' => [
//                new RequiredRule(),
//                new InRule(['Public', 'Private', 'Password'])
//            ],
//            'password' => [
//                new MaxLengthRule(255)
//            ],
//            'featured' => [
//                new BooleanRule()
//            ],
//            'allowComments' => [
//                new BooleanRule()
//            ],
//            'isReusableBlock' => [
//                new BooleanRule()
//            ],
//            'blockPreviewImage' => [
//                new MaxLengthRule(500)
//            ]
        ];
    }

    public function getTagsFormRules(): array
    {
        return [
            'categories' => [
                new ArrayRule()
            ],
            'tags' => [
                new ArrayRule()
            ],
            'customFields' => [
                new ArrayRule()
            ],
            'customFields.*.key' => [
                new RequiredRule(),
                new MaxLengthRule(255)
            ],
            'customFields.*.value' => [
                new MaxLengthRule(1000)
            ],
            'customFields.*.type' => [
                new RequiredRule(),
                new InRule(['text', 'number', 'url', 'email', 'boolean', 'date'])
            ]
        ];
    }

    public function getSocialFormRules(): array
    {
        return [
//            'socialSharing.enableSharing' => [
//                new BooleanRule()
//            ],
//            'socialSharing.platforms' => [
//                new ArrayRule()
//            ],
//            'socialSharing.shareText' => [
//                new MaxLengthRule(280)
//            ],
//            'socialSharing.shareHashtags' => [
//                new MaxLengthRule(200)
//            ],
//            'socialSharing.shareVia' => [
//                new MaxLengthRule(100)
//            ],
//            'socialAnalytics.trackShares' => [
//                new BooleanRule()
//            ],
//            'socialAnalytics.trackClicks' => [
//                new BooleanRule()
//            ],
//            'socialAnalytics.pixelIds' => [
//                new ArrayRule()
//            ],
//            'socialAnalytics.gtmEvents' => [
//                new BooleanRule()
//            ],
//            'socialProof.showFollowerCount' => [
//                new BooleanRule()
//            ],
//            'socialProof.showShareCount' => [
//                new BooleanRule()
//            ],
//            'socialProof.showRecentActivity' => [
//                new BooleanRule()
//            ],
//            'socialProof.testimonialIntegration' => [
//                new BooleanRule()
//            ],
//            'embedSettings.autoEmbedLinks' => [
//                new BooleanRule()
//            ],
//            'embedSettings.embedWidth' => [
//                new MaxLengthRule(20)
//            ],
//            'embedSettings.embedHeight' => [
//                new MaxLengthRule(20)
//            ],
//            'embedSettings.lazyLoadEmbeds' => [
//                new BooleanRule()
//            ]
        ];
    }

    public function getSettingsFormRules(): array
    {
        return [
//            'template' => [
//                new RequiredRule(),
//                new MaxLengthRule(100)
//            ],
//            'customCss' => [],
//            'customJs' => [],
//            'redirectUrl' => [
//                new UrlRule()
//            ],
//            'menuOrder' => [
//                new NumericRule()
//            ],
//            'parentPage' => [
//                new MaxLengthRule(255)
//            ],
//            'accessRoles' => [
//                new ArrayRule()
//            ],
//            'geolocation.latitude' => [
//                new NumericRule()
//            ],
//            'geolocation.longitude' => [
//                new NumericRule()
//            ],
//            'geolocation.address' => [
//                new MaxLengthRule(500)
//            ],
//            'pricing.price' => [
//                new NumericRule()
//            ],
//            'pricing.currency' => [
//                new MaxLengthRule(3)
//            ],
//            'pricing.salePrice' => [
//                new NumericRule()
//            ],
//            'pricing.recurring' => [
//                new BooleanRule()
//            ],
//            'pricing.recurringPeriod' => [
//                new InRule(['daily', 'weekly', 'monthly', 'yearly'])
//            ]
        ];
    }

    public function getSeoFormRules(): array
    {
        return [
//            'meta_title' => [
//                new MaxLengthRule(60)
//            ],
//            'mata_description' => [
//                new MaxLengthRule(160)
//            ],
//            'metaKeywords' => [
//                new MaxLengthRule(500)
//            ],
//            'canonicalUrl' => [
//                new UrlRule()
//            ],
//            'noIndex' => [
//                new BooleanRule()
//            ],
//            'noFollow' => [
//                new BooleanRule()
//            ],
//            'ogTitle' => [
//                new MaxLengthRule(60)
//            ],
//            'ogDescription' => [
//                new MaxLengthRule(200)
//            ],
//            'ogImage' => [
//                new UrlRule()
//            ],
//            'twitterCard' => [
//                new InRule(['summary', 'summary_large_image', 'app', 'player'])
//            ],
//            'schemaMarkup' => []
        ];
    }

    public function getBlocksRules(): array
    {
        return [
//            'blocks' => [
//                new ArrayRule()
//            ],
//            'blocks.*.type' => [
//                new RequiredRule(),
//                new MaxLengthRule(50)
//            ],
//            'blocks.*.id' => [
//                new RequiredRule()
//            ]
        ];
    }
}