<?php

namespace App\Services;

use App\Framework\Database\Database;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Cms\CustomFieldDefinitionRepository;
use App\Repositories\Cms\TagRepository;
use App\Services\Cms\Pages\PageService;

class EstateWebsiteService
{
    public function __construct(
        private PageService $pageService,
        private TagRepository $tagRepository,
        private CategoryRepository $categoryRepository,
        private readonly CustomFieldDefinitionRepository $customFieldRepository,
        private Database $database
    ) {}


    public function getPropertiesData(array $filters = [], int $page = 1, int $perPage = 12): array
    {
        // Build SQL query with filters
        $sql = "SELECT DISTINCT p.* FROM pages p
                LEFT JOIN page_tags pt ON p.id = pt.page_id
                LEFT JOIN tags t ON pt.tag_id = t.id
                LEFT JOIN page_categories pc ON p.id = pc.page_id
                LEFT JOIN categories c ON pc.category_id = c.id
                LEFT JOIN page_custom_fields pcf ON p.id = pcf.page_id
                LEFT JOIN custom_field_definitions cfd ON pcf.custom_field_definition_id = cfd.id
                WHERE p.status = 'published'
                AND (c.name IN ('Properties', 'Residential', 'Commercial', 'Luxury') 
                     OR EXISTS (SELECT 1 FROM page_tags pt2 
                               JOIN tags t2 ON pt2.tag_id = t2.id 
                               WHERE pt2.page_id = p.id 
                               AND t2.name IN ('house', 'apartment', 'flat', 'townhouse', 'villa', 'penthouse')))";

        $params = [];
        $conditions = [];

        // Location filter
        if (!empty($filters['location'])) {
            $conditions[] = "(p.title LIKE ? OR 
                           EXISTS (SELECT 1 FROM page_custom_fields pcf2 
                                  JOIN custom_field_definitions cfd2 ON pcf2.custom_field_definition_id = cfd2.id 
                                  WHERE pcf2.page_id = p.id 
                                  AND cfd2.key IN ('area', 'address', 'postcode') 
                                  AND pcf2.field_value LIKE ?))";
            $searchTerm = "%{$filters['location']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Property type filter
        if (!empty($filters['property_type'])) {
            $conditions[] = "(t.name = ? OR 
                           EXISTS (SELECT 1 FROM page_custom_fields pcf3 
                                  JOIN custom_field_definitions cfd3 ON pcf3.custom_field_definition_id = cfd3.id 
                                  WHERE pcf3.page_id = p.id 
                                  AND cfd3.key = 'property_type' 
                                  AND pcf3.field_value = ?))";
            $params[] = $filters['property_type'];
            $params[] = $filters['property_type'];
        }

        // Price filters
        if (!empty($filters['price_min'])) {
            $conditions[] = "EXISTS (SELECT 1 FROM page_custom_fields pcf4 
                                   JOIN custom_field_definitions cfd4 ON pcf4.custom_field_definition_id = cfd4.id 
                                   WHERE pcf4.page_id = p.id 
                                   AND cfd4.key = 'price' 
                                   AND CAST(pcf4.field_value AS UNSIGNED) >= ?)";
            $params[] = $filters['price_min'];
        }

        if (!empty($filters['price_max'])) {
            $conditions[] = "EXISTS (SELECT 1 FROM page_custom_fields pcf5 
                                   JOIN custom_field_definitions cfd5 ON pcf5.custom_field_definition_id = cfd5.id 
                                   WHERE pcf5.page_id = p.id 
                                   AND cfd5.key = 'price' 
                                   AND CAST(pcf5.field_value AS UNSIGNED) <= ?)";
            $params[] = $filters['price_max'];
        }

        // Bedroom filter
        if (!empty($filters['bedrooms'])) {
            $conditions[] = "EXISTS (SELECT 1 FROM page_custom_fields pcf6 
                                   JOIN custom_field_definitions cfd6 ON pcf6.custom_field_definition_id = cfd6.id 
                                   WHERE pcf6.page_id = p.id 
                                   AND cfd6.key = 'bedrooms' 
                                   AND CAST(pcf6.field_value AS UNSIGNED) >= ?)";
            $params[] = $filters['bedrooms'];
        }

        // Bathroom filter
        if (!empty($filters['bathrooms'])) {
            $conditions[] = "EXISTS (SELECT 1 FROM page_custom_fields pcf7 
                                   JOIN custom_field_definitions cfd7 ON pcf7.custom_field_definition_id = cfd7.id 
                                   WHERE pcf7.page_id = p.id 
                                   AND cfd7.key = 'bathrooms' 
                                   AND CAST(pcf7.field_value AS UNSIGNED) >= ?)";
            $params[] = $filters['bathrooms'];
        }

        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }

        // Count total results for pagination
        $countSql = str_replace("SELECT DISTINCT p.*", "SELECT COUNT(DISTINCT p.id) as total", $sql);
        $totalResult = $this->database->select($countSql, $params);
        $total = $totalResult[0]['total'] ?? 0;

        // Add ordering and pagination
        $sql .= " ORDER BY p.created_at DESC";
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = ($page - 1) * $perPage;

        $results = $this->database->select($sql, $params);
        $properties = [];

        foreach ($results as $row) {
            $pageData = $this->pageService->getCompletePageData($row['id']);
            if ($pageData) {
                $properties[] = [
                    'page' => $pageData,
                    'details' => $this->extractPropertyDetails($pageData),
                    'location' => $this->extractLocationData($pageData),
                    'images' => $this->extractPropertyImages($pageData)
                ];
            }
        }

        return [
            'properties' => $properties,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage),
                'has_previous' => $page > 1,
                'has_next' => $page < ceil($total / $perPage)
            ],
            'filter_options' => $this->getFilterOptions()
        ];
    }



    public function handleContactForm(array $data): array
    {
        $errors = [];

        // Validation
        if (empty($data['first_name'])) $errors['first_name'] = 'First name is required';
        if (empty($data['last_name'])) $errors['last_name'] = 'Last name is required';
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        }
        if (empty($data['message'])) $errors['message'] = 'Message is required';

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // In production, you would:
        // 1. Save enquiry to database
        // 2. Send email notification
        // 3. Send auto-response to customer
        // 4. Log the enquiry for CRM

        return ['success' => true];
    }

    private function extractPropertyDetails($page): array
    {
        $details = [
            'price' => null,
            'bedrooms' => null,
            'bathrooms' => null,
            'sqft' => null,
            'property_type' => null,
            'status' => 'for-sale'
        ];

        // Extract from custom fields first (most reliable)
        if ($page->customFields) {
            foreach ($page->customFields as $field) {
                switch (strtolower($field->customFieldDefinition->key)) {
                    case 'price':
                        $details['price'] = (int)$field->field_value;
                        break;
                    case 'bedrooms':
                        $details['bedrooms'] = (int)$field->field_value;
                        break;
                    case 'bathrooms':
                        $details['bathrooms'] = (int)$field->field_value;
                        break;
                    case 'square_feet':
                    case 'sqft':
                        $details['sqft'] = (int)$field->field_value;
                        break;
                    case 'property_type':
                        $details['property_type'] = $field->field_value;
                        break;
                }
            }
        }

        // Fallback to tags if custom fields not available
        foreach ($page->tags as $tag) {
            $tagName = strtolower($tag->name);

            if (preg_match('/(\d+)-?bed/', $tagName, $matches) && !$details['bedrooms']) {
                $details['bedrooms'] = (int)$matches[1];
            }
            if (preg_match('/(\d+)-?bath/', $tagName, $matches) && !$details['bathrooms']) {
                $details['bathrooms'] = (int)$matches[1];
            }
            if (preg_match('/([\d,]+)-?sqft/', $tagName, $matches) && !$details['sqft']) {
                $details['sqft'] = (int)str_replace(',', '', $matches[1]);
            }
            if (in_array($tagName, ['house', 'apartment', 'flat', 'townhouse', 'villa', 'penthouse']) && !$details['property_type']) {
                $details['property_type'] = $tagName;
            }
        }

        return $details;
    }

    private function extractPropertyImages($page): array
    {
        $images = [];

        if ($page->blocks) {
            foreach ($page->blocks as $block) {
                $blockData = $block->data;

                if ($block->type === 'image' && !empty($blockData['src'])) {
                    $images[] = [
                        'src' => $blockData['src'],
                        'alt' => $blockData['alt'] ?? '',
                        'caption' => $blockData['caption'] ?? ''
                    ];
                }

                if ($block->type === 'gallery' && !empty($blockData['slides'])) {
                    foreach ($blockData['slides'] as $slide) {
                        if (!empty($slide['image'])) {
                            $images[] = [
                                'src' => $slide['image'],
                                'alt' => $slide['alt'] ?? '',
                                'caption' => $slide['caption'] ?? ''
                            ];
                        }
                    }
                }
            }
        }

        return $images;
    }

    private function extractLocationData($page): array
    {
        $location = [
            'address' => '',
            'area' => '',
            'postcode' => '',
            'latitude' => null,
            'longitude' => null
        ];

        if ($page->customFields) {
            foreach ($page->customFields as $field) {

                switch (strtolower($field->customFieldDefinition->key)) {
                    case 'address':
                        $location['address'] = $field->field_value;
                        break;
                    case 'area':
                    case 'location':
                        $location['area'] = $field->field_value;
                        break;
                    case 'postcode':
                        $location['postcode'] = $field->field_value;
                        break;
                    case 'latitude':
                        $location['latitude'] = (float)$field->field_value;
                        break;
                    case 'longitude':
                        $location['longitude'] = (float)$field->field_value;
                        break;
                }
            }
        }

        // Extract area from categories if not set
        if (empty($location['area'])) {
            foreach ($page->categories as $category) {
                if (strpos(strtolower($category->name), 'london') !== false ||
                    in_array(strtolower($category->name), ['kensington', 'mayfair', 'chelsea', 'hampstead', 'richmond', 'greenwich', 'marylebone'])) {
                    $location['area'] = $category->name;
                    break;
                }
            }
        }

        return $location;
    }

    public function findPageBySlug(string $slug)
    {
        return $this->pageService->findPageBySlug($slug);
    }

    private function getFilterOptions(): array
    {
        return [
            'property_types' => ['house', 'apartment', 'flat', 'townhouse', 'villa', 'penthouse'],
            'price_ranges' => [
                ['label' => 'Up to £300,000', 'min' => 0, 'max' => 300000],
                ['label' => '£300,000 - £600,000', 'min' => 300000, 'max' => 600000],
                ['label' => '£600,000 - £1,000,000', 'min' => 600000, 'max' => 1000000],
                ['label' => '£1,000,000 - £2,000,000', 'min' => 1000000, 'max' => 2000000],
                ['label' => '£2,000,000+', 'min' => 2000000, 'max' => null]
            ],
            'bedroom_options' => [1, 2, 3, 4, 5, 6],
            'bathroom_options' => [1, 2, 3, 4, 5]
        ];
    }

    private function calculatePagination(int $total, int $currentPage, int $perPage = 12): array
    {
        $totalPages = ceil($total / $perPage);

        return [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
            'total_items' => $total,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages
        ];
    }

    public function searchPropertiesForApi(array $filters): array
    {
        $sql = "SELECT DISTINCT p.* FROM pages p
            LEFT JOIN page_tags pt ON p.id = pt.page_id
            LEFT JOIN tags t ON pt.tag_id = t.id
            LEFT JOIN page_categories pc ON p.id = pc.page_id
            LEFT JOIN categories c ON pc.category_id = c.id
            LEFT JOIN page_custom_fields pcf ON p.id = pcf.page_id
            LEFT JOIN custom_field_definitions cfd ON pcf.custom_field_definition_id = cfd.id
            WHERE p.status = 'published'
            AND (c.name IN ('Properties', 'Residential', 'Commercial', 'Luxury') 
                 OR t.name IN ('house', 'apartment', 'flat', 'townhouse', 'villa', 'penthouse'))";

        $params = [];
        $conditions = [];

        foreach ($filters as $key => $value) {
            if ($key === 'search_location') {
                $conditions[] = "(p.title LIKE ? OR 
                           EXISTS (SELECT 1 FROM page_custom_fields pcf2 
                                  JOIN custom_field_definitions cfd2 ON pcf2.custom_field_definition_id = cfd2.id 
                                  WHERE pcf2.page_id = p.id 
                                  AND cfd2.key IN ('area', 'address') 
                                  AND pcf2.field_value LIKE ?))";
                $searchTerm = "%{$value}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            } elseif ($key === 'search_property_type') {
                $conditions[] = "(t.name = ? OR 
                           EXISTS (SELECT 1 FROM page_custom_fields pcf3 
                                  JOIN custom_field_definitions cfd3 ON pcf3.custom_field_definition_id = cfd3.id 
                                  WHERE pcf3.page_id = p.id 
                                  AND cfd3.key = 'property_type' 
                                  AND pcf3.field_value = ?))";
                $params[] = $value;
                $params[] = $value;
            } elseif ($key === 'search_price_range') {
                // Handle price range like "300000-600000"
                if (strpos($value, '-') !== false) {
                    [$minPrice, $maxPrice] = explode('-', $value);
                    $conditions[] = "EXISTS (SELECT 1 FROM page_custom_fields pcf4 
                                       JOIN custom_field_definitions cfd4 ON pcf4.custom_field_definition_id = cfd4.id 
                                       WHERE pcf4.page_id = p.id 
                                       AND cfd4.key = 'price' 
                                       AND CAST(pcf4.field_value AS UNSIGNED) BETWEEN ? AND ?)";
                    $params[] = $minPrice;
                    $params[] = $maxPrice;
                } elseif ($value === '1000000+') {
                    $conditions[] = "EXISTS (SELECT 1 FROM page_custom_fields pcf5 
                                       JOIN custom_field_definitions cfd5 ON pcf5.custom_field_definition_id = cfd5.id 
                                       WHERE pcf5.page_id = p.id 
                                       AND cfd5.key = 'price' 
                                       AND CAST(pcf5.field_value AS UNSIGNED) >= 1000000)";
                }
            }
        }

        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY p.created_at DESC LIMIT 12"; // Limit for quick results

        $results = $this->database->select($sql, $params);
        $properties = [];

        foreach ($results as $row) {
            $pageData = $this->pageService->getCompletePageData($row['id']);
            if ($pageData) {
                $properties[] = [
                    'page' => $pageData->toArray(),
                    'details' => $this->extractPropertyDetails($pageData),
                    'location' => $this->extractLocationData($pageData),
                    'images' => $this->extractPropertyImages($pageData)
                ];
            }
        }

        return $properties;
    }
}