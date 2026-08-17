<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Support\SiteContext;
use App\Services\PublicContent\Widgets\PublicContentWidgetSettingsSchema;

class ConfigEditorController extends Controller
{
    public function __construct(
        private readonly PublicContentWidgetSettingsSchema $widgetSettingsSchema,
    ) {
        parent::__construct();
    }

    public function show()
    {
        $schema = $this->widgetSettingsSchema->all();
        $fileDefaults = config('public_content.widgets', []);
        $widgetDefaults = [];

        foreach ($schema as $key => $meta) {
            $widgetDefaults[$key] = array_merge(
                $this->widgetSettingsSchema->defaultsFor($key),
                is_array($fileDefaults[$key] ?? null) ? $fileDefaults[$key] : [],
            );
        }

        foreach ($fileDefaults as $key => $value) {
            if (!isset($widgetDefaults[$key]) && is_array($value)) {
                $widgetDefaults[$key] = $value;
            }
        }

        return $this->view('public-content-v2/config-editor', [
            'siteId' => SiteContext::getId(),
            'siteSlug' => SiteContext::slug(),
            'widgetDefaults' => $widgetDefaults,
            'widgetSettingsSchema' => $schema,
        ]);
    }
}
