<?php
// page.php - Refactored page renderer with improved structure and styling
// Renders all block types with proper CSS classes and semantic HTML

$page = [
    'title' => 'test mike',
    'main' => [
        'title' => 'test mike',
        'subtitle' => 'test subtitle',
    ],
    'blocks' => [
        // 1. heading
        [
            "type" => "heading",
            "title" => "",
            "text" => "heading text",
            "subtitle" => "subtitle",
            "level" => 2,
            "id" => "f256860d-620d-4352-a1c0-70c25360e4dd"
        ],
        // 2. text
        [
            "type" => "text",
            "paragraphs" => ["test abc"],
            "id" => "1b916a42-b41b-4e35-b96a-aa10103df73e"
        ],
        // 3. list
        [
            "type" => "list",
            "isEditing" => false,
            "listType" => "ul",
            "schemaType" => "none",
            "startIndex" => 1,
            "items" => ["list item 1", "list item 2"],
            "id" => "aa737aa7-f3be-4eb9-9b33-32e786c62be2"
        ],
        // 4. image
        [
            "type" => "image",
            "alt" => "alt text",
            "caption" => "caption",
            "name" => "",
            "layout" => "full",
            "id" => "73406140-4ef4-411b-a072-205d79002e6f",
            "src" => "https://via.placeholder.com/600x300",
            "linkUrl" => "",
            "noFollow" => false,
            "sponsored" => false,
            "openInNewTab" => false
        ],
        // 5. note
        [
            "type" => "note",
            "title" => "test a",
            "paragraphs" => ["test a"],
            "id" => "43a21e00-ce80-44d7-86f1-4d644862e249",
            "image" => ""
        ],
        // 6. deal
        [
            "type" => "deal",
            "brand" => "brand",
            "currency" => "$",
            "description" => "test",
            "layout" => "",
            "link" => "http://www.bbc.co.uk",
            "linkOptions" => "",
            "price" => 12,
            "productName" => "product name",
            "savingMode" => "percent",
            "title" => "label",
            "id" => "d3847024-423d-4196-a81e-74ca5c8055df",
            "noFollow" => false,
            "sponsored" => false,
            "openInNewTab" => false,
            "image" => "",
            "salePrice" => 10,
            "showDealButton" => true,
            "starBlock" => true
        ],
        // 7. product
        [
            "type" => "product",
            "brand" => "brand",
            "cons" => [],
            "currency" => "$",
            "displayAs" => "button",
            "isFeatured" => false,
            "layout" => "standard",
            "link" => "http://www.bbc.co.uk",
            "linkOptions" => "",
            "linkText" => "Buy Now",
            "name" => "label",
            "noFollow" => false,
            "openInNewTab" => false,
            "price" => 12,
            "pros" => [],
            "retailer" => "",
            "savingMode" => "",
            "showReviewPanel" => true,
            "id" => "d08189ec-ff50-4522-8456-bd47397ef797",
            "sponsored" => false,
            "image" => "",
            "productName" => "product name",
            "salePrice" => 10,
            "description" => "test",
            "review" => [
                "rating" => 4.5,
                "reviewPercent" => 90,
                "pros" => ["abc"],
                "cons" => ["def"],
                "articleId" => "",
                "articleTitle" => "",
                "articleUrl" => ""
            ]
        ],
        // 8. carousel
        [
            "type" => "carousel",
            "layout" => "carousel",
            "slides" => [
                [
                    "title" => "slide",
                    "description" => "description",
                    "alt" => "alt text",
                    "link" => "",
                    "caption" => "caption",
                    "image" => [
                        "id" => "0a8510f9-f5bc-4b9d-ab52-f3d640173a96",
                        "url" => "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUA"
                    ]
                ]
            ],
            "id" => "carousel-1"
        ],
        // 9. gallery
        [
            "type" => "gallery",
            "slides" => [
                [
                    "file" => [
                        "url" => "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUA",
                        "name" => "test image 2.jpg",
                        "alt" => "",
                        "caption" => ""
                    ],
                    "alt" => "",
                    "link" => "http://www.bbc.co.uk",
                    "noFollow" => false,
                    "sponsored" => false,
                    "openInNewTab" => false
                ],
            ],
            "id" => "6b539449-a015-4e8e-beae-1b5536d4b9a8"
        ],
        // 10. schema (question)
        [
            "type" => "schema",
            "schemaType" => "question",
            "id" => "33a2b39a-eff8-4c52-8494-0e70b4d0c7ac",
            "title" => "",
            "description" => "",
            "image" => "",
            "question" => "question",
            "answer" => "answer",
            "expansion" => "test"
        ],
        // 11. schema (how-to)
        [
            "type" => "schema",
            "schemaType" => "how-to",
            "id" => "55ed9390-8844-4d3c-a296-f125a4b1a58f",
            "title" => "title",
            "description" => "description",
            "image" => "",
            "question" => "",
            "answer" => "",
            "expansion" => ""
        ],
        // 12. section
        [
            "type" => "section",
            "navigationText" => "nav text",
            "title" => "section title",
            "headingType" => "h2",
            "excludeFromNav" => false,
            "id" => "907f44b5-b8fd-4cca-ba10-a728b9c94fbe"
        ],
        // 13. quote
        [
            "type" => "quote",
            "text" => "quote text",
            "id" => "6ed6fe23-7965-4071-98ab-7532e588ca4b",
            "attribution" => "attribution"
        ],
        // 14. buying-guide
        [
            "type" => "buying-guide",
            "cons" => [],
            "displayAs" => "button",
            "isEditing" => false,
            "linkText" => "Buy Now",
            "noFollow" => false,
            "openInNewTab" => false,
            "pros" => [],
            "showReviewPanel" => false,
            "specs" => [
                ["text" => "spec1", "value" => "value1"]
            ],
            "sponsored" => false,
            "subtitle" => "strapline",
            "title" => "buying guide",
            "url" => "",
            "id" => "2f909326-9cde-4b72-bf8b-12e22f0bbffb"
        ],
        // 15. award
        [
            "type" => "award",
            "subcategory" => "subcategory",
            "productName" => "product name",
            "caption" => "caption",
            "alt" => "alt text",
            "winner" => false,
            "rating" => 5,
            "strapline" => "strapline",
            "id" => "b6616465-1dcc-4d93-8fb2-5f14e4142241",
            "image" => "",
            "reviewPercent" => 100
        ],
        // 16. info
        [
            "type" => "info",
            "infoType" => "ingredients",
            "description" => "test test test",
            "id" => "68deda51-11e4-484f-9c3f-51d1b27f971a"
        ],
    ]
];

// ---------- Helper functions ----------
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function generateBlockClasses($type, $attributes = []) {
    $classes = ['block', 'block--' . $type];

    // Add layout-specific classes
    if (!empty($attributes['layout'])) {
        $classes[] = 'block--layout-' . $attributes['layout'];
    }

    // Add featured class for products
    if (!empty($attributes['isFeatured'])) {
        $classes[] = 'block--featured';
    }

    // Add sponsored class
    if (!empty($attributes['sponsored'])) {
        $classes[] = 'block--sponsored';
    }

    return implode(' ', $classes);
}

function getLinkAttributes($block) {
    $attrs = [];

    if (!empty($block['noFollow'])) {
        $attrs[] = 'rel="nofollow"';
    }

    if (!empty($block['sponsored']) && empty($block['noFollow'])) {
        $attrs[] = 'rel="sponsored"';
    } elseif (!empty($block['sponsored']) && !empty($block['noFollow'])) {
        $attrs[] = 'rel="nofollow sponsored"';
    }

    if (!empty($block['openInNewTab'])) {
        $attrs[] = 'target="_blank"';
    }

    return implode(' ', $attrs);
}

// ---------- Block renderers ----------
function renderHeading($b) {
    $level = max(1, min(6, (int)($b['level'] ?? 2)));
    $classes = generateBlockClasses('heading', $b);

    $out = "<div class='{$classes}' id='block-" . e($b['id']) . "'>";
    $out .= "<h{$level} class='block__title'>" . e($b['text'] ?? '') . "</h{$level}>";

    if (!empty($b['subtitle'])) {
        $out .= "<p class='block__subtitle'>" . e($b['subtitle']) . "</p>";
    }

    $out .= "</div>";
    return $out;
}

function renderText($b) {
    $classes = generateBlockClasses('text', $b);
    $paragraphs = $b['paragraphs'] ?? [];

    $out = "<div class='{$classes}' id='block-" . e($b['id']) . "'>";
    $out .= "<div class='block__content'>";

    foreach ($paragraphs as $p) {
        $out .= "<p class='block__paragraph'>" . e($p) . "</p>";
    }

    $out .= "</div></div>";
    return $out;
}

function renderList($b) {
    $type = ($b['listType'] ?? 'ul') === 'ol' ? 'ol' : 'ul';
    $classes = generateBlockClasses('list', $b);
    $start = isset($b['startIndex']) ? " start='" . (int)$b['startIndex'] . "'" : "";

    $out = "<div class='{$classes}' id='block-" . e($b['id']) . "'>";
    $out .= "<{$type}{$start} class='block__list block__list--{$type}'>";

    foreach ($b['items'] ?? [] as $item) {
        $out .= "<li class='block__list-item'>" . e($item) . "</li>";
    }

    $out .= "</{$type}></div>";
    return $out;
}

function renderImage($b) {
    $src = $b['src'] ?? ($b['url'] ?? '');
    $alt = e($b['alt'] ?? '');
    $caption = $b['caption'] ?? '';
    $link = $b['linkUrl'] ?? $b['link'] ?? '';
    $classes = generateBlockClasses('image', $b);
    $linkAttrs = getLinkAttributes($b);

    $img = "<img src='" . e($src) . "' alt='{$alt}' class='block__image' loading='lazy' />";

    if ($link) {
        $img = "<a href='" . e($link) . "' class='block__image-link' {$linkAttrs}>{$img}</a>";
    }

    $out = "<figure class='{$classes}' id='block-" . e($b['id']) . "'>";
    $out .= $img;

    if ($caption) {
        $out .= "<figcaption class='block__caption'>" . e($caption) . "</figcaption>";
    }

    $out .= "</figure>";
    return $out;
}

function renderNote($b) {
    $classes = generateBlockClasses('note', $b);

    $out = "<aside class='{$classes}' id='block-" . e($b['id']) . "'>";

    if (!empty($b['title'])) {
        $out .= "<h3 class='block__title'>" . e($b['title']) . "</h3>";
    }

    $out .= "<div class='block__content'>";
    foreach ($b['paragraphs'] ?? [] as $p) {
        $out .= "<p class='block__paragraph'>" . e($p) . "</p>";
    }
    $out .= "</div></aside>";

    return $out;
}

function renderDeal($b) {
    $classes = generateBlockClasses('deal', $b);

    $out = "<div class='{$classes}' id='block-" . e($b['id']) . "'>";
    $out .= "<div class='block__header'>";
    $out .= "<h3 class='block__title'>" . e($b['title'] ?? $b['productName'] ?? '') . "</h3>";

    if (!empty($b['brand'])) {
        $out .= "<p class='block__brand'>" . e($b['brand']) . "</p>";
    }
    $out .= "</div>";

    if (!empty($b['description'])) {
        $out .= "<p class='block__description'>" . e($b['description']) . "</p>";
    }

    // Price display
    $price = isset($b['price']) ? e($b['currency'] ?? '') . e($b['price']) : '';
    $sale = isset($b['salePrice']) ? e($b['currency'] ?? '') . "<span class='block__sale-price'>" . e($b['salePrice']) . "</span>" : '';

    if ($price) {
        $out .= "<div class='block__pricing'>";
        $out .= "<span class='block__original-price'>{$price}</span> {$sale}";
        $out .= "</div>";
    }

    // Action button
    if (!empty($b['link'])) {
        $linkAttrs = getLinkAttributes($b);

        if (!empty($b['showDealButton'])) {
            $out .= "<div class='block__actions'>";
            $out .= "<a class='block__button block__button--primary' href='" . e($b['link']) . "' {$linkAttrs}>View Deal</a>";
            $out .= "</div>";
        }
    }

    $out .= "</div>";
    return $out;
}

function renderProduct($b) {
    $classes = generateBlockClasses('product', $b);

    $out = "<div class='{$classes}' id='block-" . e($b['id']) . "'>";
    $out .= "<div class='block__header'>";
    $out .= "<h3 class='block__title'>" . e($b['productName'] ?? $b['name'] ?? '') . "</h3>";

    if (!empty($b['brand'])) {
        $out .= "<p class='block__brand'>" . e($b['brand']) . "</p>";
    }
    $out .= "</div>";

    if (!empty($b['description'])) {
        $out .= "<p class='block__description'>" . e($b['description']) . "</p>";
    }

    // Price display
    $price = isset($b['price']) ? e($b['currency'] ?? '') . e($b['price']) : '';
    $sale = isset($b['salePrice']) ? e($b['currency'] ?? '') . "<span class='block__sale-price'>" . e($b['salePrice']) . "</span>" : '';

    if ($price) {
        $out .= "<div class='block__pricing'>";
        $out .= "<span class='block__original-price'>{$price}</span> {$sale}";
        $out .= "</div>";
    }

    // Review panel
    if (!empty($b['showReviewPanel']) && !empty($b['review'])) {
        $review = $b['review'];
        $out .= "<div class='block__review'>";
        $out .= "<div class='block__rating'>";
        $out .= "<span class='block__rating-score'>" . e($review['rating'] ?? '') . "</span>";
        $out .= "<span class='block__rating-percent'>" . e($review['reviewPercent'] ?? '') . "%</span>";
        $out .= "</div>";

        if (!empty($review['pros'])) {
            $out .= "<div class='block__pros'>";
            $out .= "<h4 class='block__pros-title'>Pros</h4>";
            $out .= "<ul class='block__pros-list'>";
            foreach ($review['pros'] as $pro) {
                $out .= "<li class='block__pro-item'>" . e($pro) . "</li>";
            }
            $out .= "</ul></div>";
        }

        if (!empty($review['cons'])) {
            $out .= "<div class='block__cons'>";
            $out .= "<h4 class='block__cons-title'>Cons</h4>";
            $out .= "<ul class='block__cons-list'>";
            foreach ($review['cons'] as $con) {
                $out .= "<li class='block__con-item'>" . e($con) . "</li>";
            }
            $out .= "</ul></div>";
        }

        $out .= "</div>";
    }

    // Action button
    if (!empty($b['link'])) {
        $linkAttrs = getLinkAttributes($b);
        $out .= "<div class='block__actions'>";
        $out .= "<a class='block__button block__button--primary' href='" . e($b['link']) . "' {$linkAttrs}>" . e($b['linkText'] ?? 'Buy Now') . "</a>";
        $out .= "</div>";
    }

    $out .= "</div>";
    return $out;
}

function renderCarousel($b) {
    $classes = generateBlockClasses('carousel', $b);

    $out = "<div class='{$classes}' id='block-" . e($b['id']) . "'>";
    $out .= "<div class='block__carousel-container'>";

    foreach ($b['slides'] ?? [] as $index => $slide) {
        $img = $slide['image']['url'] ?? ($slide['image'] ?? '');
        $out .= "<div class='block__slide' data-slide='{$index}'>";

        if ($img) {
            $out .= "<img src='" . e($img) . "' alt='" . e($slide['alt'] ?? '') . "' class='block__slide-image' loading='lazy' />";
        }

        if (!empty($slide['title']) || !empty($slide['description'])) {
            $out .= "<div class='block__slide-content'>";

            if (!empty($slide['title'])) {
                $out .= "<h4 class='block__slide-title'>" . e($slide['title']) . "</h4>";
            }

            if (!empty($slide['description'])) {
                $out .= "<p class='block__slide-description'>" . e($slide['description']) . "</p>";
            }

            $out .= "</div>";
        }

        if (!empty($slide['caption'])) {
            $out .= "<small class='block__slide-caption'>" . e($slide['caption']) . "</small>";
        }

        $out .= "</div>";
    }

    $out .= "</div></div>";
    return $out;
}

function renderGallery($b) {
    $classes = generateBlockClasses('gallery', $b);

    $out = "<div class='{$classes}' id='block-" . e($b['id']) . "'>";
    $out .= "<div class='block__gallery-grid'>";

    foreach ($b['slides'] ?? [] as $slide) {
        $file = $slide['file'] ?? $slide['image'] ?? null;
        $src = $file['url'] ?? ($file['src'] ?? '');
        $name = $file['name'] ?? '';
        $caption = $file['caption'] ?? $slide['caption'] ?? '';
        $linkAttrs = getLinkAttributes($slide);

        $out .= "<figure class='block__gallery-item'>";

        if ($src) {
            $img = "<img src='" . e($src) . "' alt='" . e($file['alt'] ?? '') . "' class='block__gallery-image' loading='lazy' />";

            if (!empty($slide['link'])) {
                $img = "<a href='" . e($slide['link']) . "' class='block__gallery-link' {$linkAttrs}>{$img}</a>";
            }

            $out .= $img;
        }

        if ($name || $caption) {
            $out .= "<figcaption class='block__gallery-caption'>";
            if ($name) $out .= e($name);
            if ($name && $caption) $out .= " - ";
            if ($caption) $out .= e($caption);
            $out .= "</figcaption>";
        }

        $out .= "</figure>";
    }

    $out .= "</div></div>";
    return $out;
}

function renderSchema($b) {
    $type = $b['schemaType'] ?? '';
    $classes = generateBlockClasses('schema', ['schemaType' => $type]);

    $out = "<div class='{$classes} block--schema-{$type}' id='block-" . e($b['id']) . "'>";

    if ($type === 'question') {
        $out .= "<div class='block__question-answer'>";
        $out .= "<h3 class='block__question'>" . e($b['question'] ?? '') . "</h3>";
        $out .= "<div class='block__answer'>" . e($b['answer'] ?? '') . "</div>";

        if (!empty($b['expansion'])) {
            $out .= "<div class='block__expansion'>" . e($b['expansion']) . "</div>";
        }

        $out .= "</div>";
    } elseif ($type === 'how-to') {
        $out .= "<div class='block__how-to'>";
        $out .= "<h3 class='block__title'>" . e($b['title'] ?? 'How-to') . "</h3>";
        $out .= "<div class='block__description'>" . e($b['description'] ?? '') . "</div>";
        $out .= "</div>";
    } else {
        $out .= "<div class='block__debug'>";
        $out .= "<pre>" . e(json_encode($b, JSON_PRETTY_PRINT)) . "</pre>";
        $out .= "</div>";
    }

    $out .= "</div>";
    return $out;
}

function renderSection($b) {
    $classes = generateBlockClasses('section', $b);
    $heading = $b['headingType'] ?? 'h2';

    $out = "<section class='{$classes}' id='block-" . e($b['id']) . "'>";
    $out .= "<{$heading} class='block__title'>" . e($b['title'] ?? '') . "</{$heading}>";

    if (!empty($b['navigationText'])) {
        $out .= "<p class='block__nav-text'>" . e($b['navigationText']) . "</p>";
    }

    $out .= "</section>";
    return $out;
}

function renderQuote($b) {
    $classes = generateBlockClasses('quote', $b);

    $out = "<blockquote class='{$classes}' id='block-" . e($b['id']) . "'>";
    $out .= "<div class='block__quote-text'>" . e($b['text'] ?? '') . "</div>";

    if (!empty($b['attribution'])) {
        $out .= "<footer class='block__attribution'>";
        $out .= "<cite>" . e($b['attribution']) . "</cite>";
        $out .= "</footer>";
    }

    $out .= "</blockquote>";
    return $out;
}

function renderBuyingGuide($b) {
    $classes = generateBlockClasses('buying-guide', $b);

    $out = "<div class='{$classes}' id='block-" . e($b['id']) . "'>";
    $out .= "<div class='block__header'>";
    $out .= "<h3 class='block__title'>" . e($b['title'] ?? '') . "</h3>";

    if (!empty($b['subtitle'])) {
        $out .= "<p class='block__subtitle'>" . e($b['subtitle']) . "</p>";
    }
    $out .= "</div>";

    if (!empty($b['specs'])) {
        $out .= "<div class='block__specs'>";
        $out .= "<h4 class='block__specs-title'>Specifications</h4>";
        $out .= "<dl class='block__specs-list'>";

        foreach ($b['specs'] as $spec) {
            $out .= "<dt class='block__spec-label'>" . e($spec['text'] ?? '') . "</dt>";
            $out .= "<dd class='block__spec-value'>" . e($spec['value'] ?? '') . "</dd>";
        }

        $out .= "</dl></div>";
    }

    if (!empty($b['linkText']) && !empty($b['url'])) {
        $linkAttrs = getLinkAttributes($b);
        $out .= "<div class='block__actions'>";
        $out .= "<a href='" . e($b['url']) . "' class='block__button block__button--primary' {$linkAttrs}>" . e($b['linkText']) . "</a>";
        $out .= "</div>";
    }

    $out .= "</div>";
    return $out;
}

function renderAward($b) {
    $classes = generateBlockClasses('award', $b);

    $out = "<div class='{$classes}' id='block-" . e($b['id']) . "'>";

    if (!empty($b['winner'])) {
        $out .= "<div class='block__badge block__badge--winner'>Winner</div>";
    }

    $out .= "<div class='block__header'>";
    $out .= "<h3 class='block__title'>" . e($b['productName'] ?? '') . "</h3>";

    if (!empty($b['strapline'])) {
        $out .= "<p class='block__strapline'>" . e($b['strapline']) . "</p>";
    }
    $out .= "</div>";

    $out .= "<div class='block__rating'>";
    $out .= "<span class='block__rating-score'>" . e($b['rating'] ?? '') . "</span>";
    $out .= "<span class='block__rating-percent'>" . e($b['reviewPercent'] ?? '') . "%</span>";
    $out .= "</div>";

    if (!empty($b['caption'])) {
        $out .= "<p class='block__caption'>" . e($b['caption']) . "</p>";
    }

    $out .= "</div>";
    return $out;
}

function renderInfo($b) {
    $classes = generateBlockClasses('info', $b);

    $out = "<div class='{$classes}' id='block-" . e($b['id']) . "'>";
    $out .= "<h3 class='block__title'>Info: " . e($b['infoType'] ?? '') . "</h3>";
    $out .= "<div class='block__content'>" . e($b['description'] ?? '') . "</div>";
    $out .= "</div>";

    return $out;
}

function renderUnknown($b) {
    $classes = generateBlockClasses('unknown', $b);

    return "<div class='{$classes}' id='block-" . e($b['id']) . "'>" .
        "<div class='block__debug'><pre>" . e(json_encode($b, JSON_PRETTY_PRINT)) . "</pre></div>" .
        "</div>";
}

function renderBlock($b) {
    $type = $b['type'] ?? $b['schemaType'] ?? 'unknown';

    switch ($type) {
        case 'heading': return renderHeading($b);
        case 'text': return renderText($b);
        case 'list': return renderList($b);
        case 'image': return renderImage($b);
        case 'note': return renderNote($b);
        case 'deal': return renderDeal($b);
        case 'product': return renderProduct($b);
        case 'carousel': return renderCarousel($b);
        case 'gallery': return renderGallery($b);
        case 'schema': return renderSchema($b);
        case 'section': return renderSection($b);
        case 'quote': return renderQuote($b);
        case 'buying-guide': return renderBuyingGuide($b);
        case 'award': return renderAward($b);
        case 'info': return renderInfo($b);
        default: return renderUnknown($b);
    }
}

// ---------- Output page ----------
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($page['title']); ?></title>
    <style>
        /* CSS Custom Properties for easy customization */
        :root {
            --color-primary: #007bff;
            --color-primary-hover: #0056b3;
            --color-text: #333;
            --color-text-light: #666;
            --color-text-muted: #999;
            --color-background: #fff;
            --color-background-light: #f8f9fa;
            --color-border: #dee2e6;
            --color-border-light: #e9ecef;
            --color-success: #28a745;
            --color-warning: #ffc107;
            --color-danger: #dc3545;
            --color-gold: #ffd700;

            --font-family-base: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --font-family-mono: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;

            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 3rem;

            --border-radius: 0.375rem;
            --border-radius-lg: 0.5rem;
            --box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --box-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* Base styles */
        * {
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family-base);
            line-height: 1.6;
            color: var(--color-text);
            background-color: var(--color-background);
            margin: 0;
            padding: var(--spacing-lg);
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Typography */
        h1, h2, h3, h4, h5, h6 {
            margin: 0 0 var(--spacing-md);
            font-weight: 600;
            line-height: 1.25;
        }

        p {
            margin: 0 0 var(--spacing-md);
        }

        /* Block base styles */
        .block {
            margin-bottom: var(--spacing-xl);
            border: 1px solid var(--color-border-light);
            border-radius: var(--border-radius);
            background-color: var(--color-background);
            overflow: hidden;
        }

        .block__title {
            margin: 0 0 var(--spacing-sm);
            color: var(--color-text);
        }

        .block__subtitle {
            margin: 0 0 var(--spacing-md);
            color: var(--color-text-light);
            font-size: 0.9em;
        }

        .block__content {
            padding: var(--spacing-lg);
        }

        .block__paragraph {
            margin: 0 0 var(--spacing-md);
        }

        .block__paragraph:last-child {
            margin-bottom: 0;
        }

        /* Heading block */
        .block--heading {
            border: none;
            background: none;
            padding: 0;
        }

        .block--heading .block__title {
            margin-bottom: var(--spacing-sm);
        }

        /* Text block */
        .block--text {
            padding: var(--spacing-lg);
        }

        /* List block */
        .block--list {
            padding: var(--spacing-lg);
        }

        .block__list {
            margin: 0;
            padding-left: var(--spacing-xl);
        }

        .block__list-item {
            margin-bottom: var(--spacing-sm);
        }

        /* Image block */
        .block--image {
            border: none;
            padding: 0;
        }

        .block--layout-full .block__image {
            width: 100%;
            height: auto;
            display: block;
        }

        .block__caption {
            padding: var(--spacing-md);
            font-size: 0.875em;
            color: var(--color-text-light);
            font-style: italic;
        }

        .block__image-link {
            display: block;
        }

        /* Note block */
        .block--note {
            background-color: var(--color-background-light);
            border-left: 4px solid var(--color-primary);
        }

        .block--note .block__content {
            padding: var(--spacing-lg);
        }

        /* Deal and Product blocks */
        .block--deal,
        .block--product {
            border: 1px solid var(--color-border);
            background-color: var(--color-background);
        }

        .block--deal .block__header,
        .block--product .block__header {
            padding: var(--spacing-lg) var(--spacing-lg) 0;
        }

        .block--deal .block__brand,
        .block--product .block__brand {
            font-size: 0.875em;
            color: var(--color-text-muted);
            margin: var(--spacing-xs) 0 var(--spacing-md);
        }

        .block--deal .block__description,
        .block--product .block__description {
            padding: 0 var(--spacing-lg);
            margin-bottom: var(--spacing-md);
        }

        .block__pricing {
            padding: 0 var(--spacing-lg);
            margin-bottom: var(--spacing-md);
        }

        .block__original-price {
            text-decoration: line-through;
            color: var(--color-text-muted);
            margin-right: var(--spacing-sm);
        }

        .block__sale-price {
            font-weight: 600;
            color: var(--color-success);
            font-size: 1.125em;
        }

        .block__actions {
            padding: var(--spacing-lg);
        }

        .block__button {
            display: inline-block;
            padding: var(--spacing-sm) var(--spacing-lg);
            background-color: var(--color-primary);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .block__button:hover {
            background-color: var(--color-primary-hover);
            transform: translateY(-1px);
        }

        .block__button--primary {
            background-color: var(--color-primary);
        }

        /* Review styles */
        .block__review {
            background-color: var(--color-background-light);
            margin: var(--spacing-md) var(--spacing-lg);
            padding: var(--spacing-lg);
            border-radius: var(--border-radius);
            border: 1px solid var(--color-border-light);
        }

        .block__rating {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-md);
        }

        .block__rating-score {
            font-size: 1.25em;
            font-weight: 600;
            color: var(--color-primary);
        }

        .block__rating-percent {
            font-size: 0.875em;
            color: var(--color-text-light);
        }

        .block__pros,
        .block__cons {
            margin-bottom: var(--spacing-md);
        }

        .block__pros-title,
        .block__cons-title {
            font-size: 1em;
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
        }

        .block__pros-title {
            color: var(--color-success);
        }

        .block__cons-title {
            color: var(--color-danger);
        }

        .block__pros-list,
        .block__cons-list {
            margin: 0;
            padding-left: var(--spacing-lg);
        }

        .block__pro-item,
        .block__con-item {
            margin-bottom: var(--spacing-xs);
        }

        /* Carousel block */
        .block--carousel .block__carousel-container {
            display: flex;
            overflow-x: auto;
            gap: var(--spacing-md);
            padding: var(--spacing-lg);
        }

        .block__slide {
            flex: 0 0 300px;
            background-color: var(--color-background-light);
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .block__slide-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .block__slide-content {
            padding: var(--spacing-md);
        }

        .block__slide-title {
            margin: 0 0 var(--spacing-sm);
        }

        .block__slide-description {
            margin: 0 0 var(--spacing-sm);
            font-size: 0.875em;
            color: var(--color-text-light);
        }

        .block__slide-caption {
            display: block;
            padding: var(--spacing-sm) var(--spacing-md);
            font-size: 0.75em;
            color: var(--color-text-muted);
            background-color: var(--color-background);
        }

        /* Gallery block */
        .block--gallery .block__gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: var(--spacing-md);
            padding: var(--spacing-lg);
        }

        .block__gallery-item {
            margin: 0;
            background-color: var(--color-background-light);
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .block__gallery-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            display: block;
        }

        .block__gallery-caption {
            padding: var(--spacing-sm);
            font-size: 0.75em;
            color: var(--color-text-light);
        }

        /* Schema blocks */
        .block--schema {
            padding: var(--spacing-lg);
        }

        .block__question-answer .block__question {
            color: var(--color-primary);
            margin-bottom: var(--spacing-md);
        }

        .block__answer {
            margin-bottom: var(--spacing-md);
            padding-left: var(--spacing-md);
            border-left: 3px solid var(--color-primary);
        }

        .block__expansion {
            font-size: 0.9em;
            color: var(--color-text-light);
        }

        /* Quote block */
        .block--quote {
            border-left: 4px solid var(--color-primary);
            background-color: var(--color-background-light);
            padding: var(--spacing-xl);
            margin: var(--spacing-xl) 0;
            font-size: 1.125em;
        }

        .block__quote-text {
            font-style: italic;
            margin-bottom: var(--spacing-md);
        }

        .block__attribution {
            font-size: 0.9em;
            color: var(--color-text-light);
        }

        .block__attribution cite {
            font-style: normal;
            font-weight: 500;
        }

        /* Section block */
        .block--section {
            border: none;
            background: none;
            padding: var(--spacing-xl) 0;
        }

        .block--section .block__nav-text {
            font-size: 0.875em;
            color: var(--color-text-muted);
        }

        /* Buying guide block */
        .block--buying-guide {
            padding: var(--spacing-lg);
        }

        .block--buying-guide .block__header {
            margin-bottom: var(--spacing-lg);
        }

        .block__specs {
            background-color: var(--color-background-light);
            padding: var(--spacing-lg);
            border-radius: var(--border-radius);
            margin-bottom: var(--spacing-lg);
        }

        .block__specs-title {
            margin: 0 0 var(--spacing-md);
        }

        .block__specs-list {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: var(--spacing-sm) var(--spacing-md);
            margin: 0;
        }

        .block__spec-label {
            font-weight: 500;
            margin: 0;
        }

        .block__spec-value {
            margin: 0;
        }

        /* Award block */
        .block--award {
            position: relative;
            padding: var(--spacing-lg);
            background: linear-gradient(135deg, var(--color-background) 0%, var(--color-background-light) 100%);
        }

        .block__badge {
            position: absolute;
            top: var(--spacing-md);
            right: var(--spacing-md);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--border-radius);
            font-size: 0.75em;
            font-weight: 600;
            text-transform: uppercase;
        }

        .block__badge--winner {
            background-color: var(--color-gold);
            color: #000;
        }

        .block--award .block__rating {
            justify-content: flex-start;
        }

        /* Info block */
        .block--info {
            background-color: var(--color-background-light);
            border-left: 4px solid var(--color-warning);
            padding: var(--spacing-lg);
        }

        /* Sponsored content indicator */
        .block--sponsored::before {
            content: "Sponsored";
            position: absolute;
            top: var(--spacing-sm);
            right: var(--spacing-sm);
            background-color: var(--color-text-muted);
            color: white;
            padding: var(--spacing-xs) var(--spacing-sm);
            font-size: 0.75em;
            border-radius: var(--border-radius);
            text-transform: uppercase;
        }

        .block--sponsored {
            position: relative;
        }

        /* Debug styles */
        .block__debug pre {
            white-space: pre-wrap;
            font-size: 0.75em;
            color: var(--color-text-light);
            background-color: var(--color-background-light);
            padding: var(--spacing-md);
            border-radius: var(--border-radius);
            overflow-x: auto;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            body {
                padding: var(--spacing-md);
            }

            .block--carousel .block__carousel-container {
                padding: var(--spacing-md);
            }

            .block__slide {
                flex: 0 0 250px;
            }

            .block--gallery .block__gallery-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: var(--spacing-sm);
                padding: var(--spacing-md);
            }

            .block__specs-list {
                grid-template-columns: 1fr;
                gap: var(--spacing-sm);
            }

            .block__spec-label {
                font-weight: 600;
            }
        }

        /* Site-specific overrides placeholder */
        /* Add site-specific styles here - they will override the base styles above */
    </style>
</head>
<body>
<header class="site-header">
    <h1><?php echo e($page['main']['title']); ?></h1>
    <?php if (!empty($page['main']['subtitle'])): ?>
        <p class="site-subtitle"><?php echo e($page['main']['subtitle']); ?></p>
    <?php endif; ?>
</header>

<main class="site-content">
    <?php foreach ($page['blocks'] as $block): ?>
        <?php echo renderBlock($block); ?>
    <?php endforeach; ?>
</main>

<footer class="site-footer">
    <p>Rendered from structure file. Built with semantic HTML and CSS.</p>
</footer>
</body>
</html>