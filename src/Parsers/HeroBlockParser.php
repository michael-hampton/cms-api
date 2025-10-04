<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Models\CustomFieldDefinition;

class HeroBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'hero';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [new RequiredRule(), new MaxLengthRule(255)],
            'subtitle' => [new MaxLengthRule(500)],
            'backgroundImage' => [new MaxLengthRule(500)],
            'ctaText' => [new MaxLengthRule(50)],
            'ctaUrl' => [new MaxLengthRule(500)],
            'secondaryCtaText' => [new MaxLengthRule(50)],
            'secondaryCtaUrl' => [new MaxLengthRule(500)],
            'showSearch' => [new BooleanRule()]
        ];
    }

    public function parse(array $data): array
    {
        return [
            'title' => trim($data['title'] ?? ''),
            'subtitle' => trim($data['subtitle'] ?? ''),
            'backgroundImage' => $data['backgroundImage'] ?? null,
            'ctaText' => $data['ctaText'] ?? 'Get Started',
            'ctaUrl' => $data['ctaUrl'] ?? '#',
            'secondaryCtaText' => $data['secondaryCtaText'] ?? '',
            'secondaryCtaUrl' => $data['secondaryCtaUrl'] ?? '#',
            'showSearch' => (bool)($data['showSearch'] ?? false),
            'formatted_title' => htmlspecialchars($data['title'] ?? ''),
            'formatted_subtitle' => htmlspecialchars($data['subtitle'] ?? ''),
            'has_secondary_cta' => !empty($data['secondaryCtaText']),
            'has_background' => !empty($data['backgroundImage'])
        ];
    }

    public function generateHtml(array $parsedData, ?int $pageId = null): string
    {
        $backgroundStyle = '';
        if ($parsedData['has_background']) {
            $backgroundStyle = "background-image: url('{$parsedData['backgroundImage']}');";
        }

        $html = "<section class=\"hero-block\" style=\"{$backgroundStyle}\">";
        $html .= "<div class=\"hero-content\">";

        $html .= "<h1 class=\"hero-title\">{$parsedData['formatted_title']}</h1>";

        if (!empty($parsedData['subtitle'])) {
            $html .= "<p class=\"hero-subtitle\">{$parsedData['formatted_subtitle']}</p>";
        }

        $html .= "<div class=\"hero-actions\">";
        $html .= "<a href=\"{$parsedData['ctaUrl']}\" class=\"hero-cta\">{$parsedData['ctaText']}</a>";

        if ($parsedData['has_secondary_cta']) {
            $html .= "<a href=\"{$parsedData['secondaryCtaUrl']}\" class=\"hero-secondary-cta\">{$parsedData['secondaryCtaText']}</a>";
        }
        $html .= "</div>";

        if ($parsedData['showSearch']) {

            $searchableFields = CustomFieldDefinition::where('is_searchable', true)
                ->join('page_custom_fields', 'page_custom_fields.custom_field_definition_id', '=', 'custom_field_definitions.id')
                ->when($pageId, function ($query) use ($pageId) {
                    return $query->where('page_id', $pageId);
                })
                ->get();

            $fieldCount = count($searchableFields);
            $gridCols = $fieldCount > 0 ? min($fieldCount + 1, 4) : 3;

            $html .= "<div class=\"search-section\">";
            $html .= "<form class=\"search-form\" method=\"GET\" action=\"/properties\" id=\"hero-search-form\">";

            foreach ($searchableFields as $field) {
                if ($field->type === 'text') {
                    $html .= "<div class=\"form-group\">";
                    $html .= "<label>" . htmlspecialchars($field->name) . "</label>";
                    $html .= "<input type=\"text\" name=\"{$field->key}\" placeholder=\"Enter " . strtolower($field->name) . "\" class=\"form-input search-field\">";
                    $html .= "</div>";
                } elseif ($field->type === 'select' && $field->options) {
                    $options = $field->options ?: [];
                    $html .= "<div class=\"form-group\">";
                    $html .= "<label>" . htmlspecialchars($field->name) . "</label>";
                    $html .= "<select name=\"{$field->key}\" class=\"form-select search-field\">";
                    $html .= "<option value=\"\">Any " . htmlspecialchars($field->name) . "</option>";
                    foreach ($options as $value => $label) {
                        $html .= "<option value=\"" . htmlspecialchars($value) . "\">" . htmlspecialchars($label) . "</option>";
                    }
                    $html .= "</select>";
                    $html .= "</div>";
                }
            }

            $html .= "<button type=\"submit\" class=\"cta-button\">Search Properties</button>";
            $html .= "</form>";
            $html .= "</div>";

            // Search results container
            $html .= "<div id=\"hero-search-results\" class=\"hero-search-results\" style=\"display: none;\">";
            $html .= "<div class=\"search-results-header\">";
            $html .= "<h3>Search Results</h3>";
            $html .= "<button class=\"close-results\" onclick=\"closeSearchResults()\">&times;</button>";
            $html .= "</div>";
            $html .= "<div class=\"search-results-grid\" id=\"search-results-content\">";
            $html .= "<!-- Results will be loaded here -->";
            $html .= "</div>";
            $html .= "<div class=\"search-results-footer\">";
            $html .= "<a href=\"#\" id=\"view-all-results\" class=\"btn btn-outline\">View All Properties</a>";
            $html .= "</div>";
            $html .= "</div>";

            // Add search JavaScript
            $html .= "<script>
    document.getElementById('hero-search-form').addEventListener('submit', function(e) {
        e.preventDefault();
        performHeroSearch();
    });
    
    // Also trigger search on field changes for live search
    document.querySelectorAll('.search-field').forEach(field => {
        field.addEventListener('change', function() {
            if (document.getElementById('hero-search-results').style.display !== 'none') {
                performHeroSearch();
            }
        });
    });
    
    function performHeroSearch() {
        const form = document.getElementById('hero-search-form');
        const formData = new FormData(form);
        const params = new URLSearchParams();
        
        for (let [key, value] of formData.entries()) {
            if (value) params.append(key, value);
        }
        
        // Show loading
        const resultsContainer = document.getElementById('hero-search-results');
        const resultsContent = document.getElementById('search-results-content');
        
        resultsContainer.style.display = 'block';
        resultsContent.innerHTML = '<div class=\"search-loading\">Searching properties...</div>';
        
        // Fetch results
        fetch('/api/search-properties?' + params.toString())
            .then(response => response.json())
            .then(data => {
                displaySearchResults(data.data);
                
                // Update view all link with search params
                const viewAllLink = document.getElementById('view-all-results');
                viewAllLink.href = '/properties?' + params.toString();
            })
            .catch(error => {
                console.error('Search error:', error);
                resultsContent.innerHTML = '<div class=\"search-error\">Error searching properties. Please try again.</div>';
            });
    }
    
    function displaySearchResults(data) {
        const resultsContent = document.getElementById('search-results-content');
                
        if (data.properties.length === 0) {
            resultsContent.innerHTML = '<div class=\"no-results\">No properties found matching your criteria.</div>';
            return;
        }
        
        let html = '';
        data.properties.slice(0, 6).forEach(property => { // Show max 6 results
            html += `
                <div class=\"search-result-card\">
                    <div class=\"result-image\">
                        <img src=\"\${property.images[0]?.src || '/images/placeholder.jpg'}\" alt=\"\${property.page.title}\">
                        <div class=\"result-price\">£\${property.details.price ? property.details.price.toLocaleString() : 'POA'}</div>
                    </div>
                    <div class=\"result-content\">
                        <h4 class=\"result-title\">\${property.page.title}</h4>
                        <div class=\"result-location\">📍 \${property.location.area || 'London'}</div>
                        <div class=\"result-features\">
                            \${property.details.bedrooms ? `🛏️ \${property.details.bedrooms} bed ` : ''}
                            \${property.details.bathrooms ? `🚿 \${property.details.bathrooms} bath ` : ''}
                            \${property.details.sqft ? `📐 \${property.details.sqft.toLocaleString()} sq ft` : ''}
                        </div>
                        <a href=\"/property/\${property.page.id}\" class=\"result-link\">View Details</a>
                    </div>
                </div>
            `;
        });
        
        resultsContent.innerHTML = html;
        
        // Update results count
        document.querySelector('.search-results-header h3').textContent = 
            `Search Results (\${data.properties.length} found)`;
    }
    
    function closeSearchResults() {
        document.getElementById('hero-search-results').style.display = 'none';
    }
    </script>";
        }

        $html .= "</div>";
        $html .= "</section>";

        return $html;
    }
}