<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Models\CustomFieldDefinition;
use App\Models\Menu;
use App\Models\Page;
use App\Services\Cms\Pages\BlockParserService;
use App\Services\EstateWebsiteService;

class EstateWebsiteController extends Controller
{
    public function __construct(
        private EstateWebsiteService $websiteService,
        private BlockParserService $blockParserService
    ) {
        parent::__construct();
    }

    public function properties(Request $request): Response
    {
        $menu = Menu::where('is_active', true)->with(['items'])->first();
        $page = Page::where('slug', 'properties')->first();

        $filters = [
            'location' => $request->get('location', ''),
            'property_type' => $request->get('property_type', ''),
            'price_min' => $request->get('price_min', ''),
            'price_max' => $request->get('price_max', ''),
            'bedrooms' => $request->get('bedrooms', ''),
            'bathrooms' => $request->get('bathrooms', ''),
            'page' => (int)$request->get('page', 1)
        ];

        $data = $this->websiteService->getPropertiesData($filters, $filters['page']);

        return $this->view('estate/properties', [
            'menu' => $menu,
            'properties' => $data['properties'],
            'page' => $page,
            'filters' => $filters,
            'pagination' => $data['pagination']
        ]);
    }

    public function submitContact(Request $request): Response
    {
        $result = $this->websiteService->handleContactForm($request->all());

        $page = $this->websiteService->findPageBySlug('contact');

        if ($result['success']) {
            return $this->view('estate/contact', [
                'page' => $page,
                'blockParserService' => $this->blockParserService,
                'success_message' => 'Thank you for your message. We will get back to you soon.'
            ]);
        } else {
            return $this->view('estate/contact', [
                'page' => $page,
                'blockParserService' => $this->blockParserService,
                'errors' => $result['errors'],
                'old_data' => $request->all()
            ]);
        }
    }

    public function search(Request $request): Response
    {
        $filters = [];

        // Get all searchable custom fields
        $searchableFields = CustomFieldDefinition::where('is_searchable', true)
            ->join('page_custom_fields', 'page_custom_fields.custom_field_definition_id', '=', 'custom_field_definitions.id')
            ->get();

        foreach ($searchableFields as $field) {
            $value = $request->get($field->key);
            if (!empty($value)) {
                $filters[$field->key] = $value;
            }
        }

        $properties = $this->websiteService->searchPropertiesForApi($filters);

        return $this->jsonResponse([
            'properties' => $properties,
            'total' => count($properties)
        ]);
    }
}