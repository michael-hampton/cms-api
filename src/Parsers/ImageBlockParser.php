<?php

namespace App\Parsers;

use App\Enums\Alignment;
use App\Enums\ImageLayout;
use App\Enums\ImageRights;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\EnumRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MinLengthRule;
use App\Models\Image;
use App\Validation\Custom\AltTextLengthRule;
use App\Validation\Custom\ImageLayoutRule;
use App\Validation\Custom\ImageSourceRule;

class ImageBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'image';
    }

    public function getValidationRules(): array
    {
        return [
            'src' => [
                new RequiredRule(),
                new ImageSourceRule()
            ],
            'caption' => [
                new MaxLengthRule(500)
            ],
            'alt' => [
                new RequiredRule(),
                new AltTextLengthRule()
            ],
            'linkUrl' => [
                new UrlRule()
            ],
            'noFollow' => [
                new BooleanRule()
            ],
            'sponsored' => [
               new BooleanRule()
            ],
            'openInNewTab' => [
               new BooleanRule()
            ],
            'layout' => [
                new EnumRule(ImageLayout::class)
            ],
            'alignment' => [
                new EnumRule(Alignment::class)
            ]
        ];
    }

    public function parse(array $data): array
    {
        $src = trim($data['src'] ?? '');
        $caption = trim($data['caption'] ?? '');
        $alt = trim($data['alt'] ?? '');
        $linkUrl = trim($data['linkUrl'] ?? '');
        $noFollow = $this->parseBooleanValue($data['noFollow'] ?? false);
        $sponsored = $this->parseBooleanValue($data['sponsored'] ?? false);
        $openInNewTab = $this->parseBooleanValue($data['openInNewTab'] ?? false);
        $layout = $data['layout'] ?? 'full';
        $endorsements = $data['endorsements'] ?? [];

        $imageData = $this->getImageData($data['image_id'] ?? null);

        $credit = trim($imageData['credit'] ?? '');
        $imageRights = $imageData['image_rights'] ?? null;

        $imageInfo = $this->extractImageInfo($src);

        return [
            'src' => $src,
            'caption' => $caption,
            'alt' => $alt,
            'credit' => $credit,
            'image_rights' => $imageRights,
            'should_display_credit' => $this->shouldDisplayCredit($imageRights, $credit),
            'linkUrl' => $linkUrl,
            'noFollow' => $noFollow,
            'sponsored' => $sponsored,
            'openInNewTab' => $openInNewTab,
            'layout' => $layout,
            'has_caption' => !empty($caption),
            'has_credit' => !empty($credit),
            'has_link' => !empty($linkUrl),
            'is_external_link' => $this->isExternalLink($linkUrl),
            'formatted_caption' => htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'),
            'formatted_alt' => htmlspecialchars($alt, ENT_QUOTES, 'UTF-8'),
            'formatted_credit' => htmlspecialchars($credit, ENT_QUOTES, 'UTF-8'),
            'image_type' => $imageInfo['type'],
            'image_extension' => $imageInfo['extension'],
            'is_saved_image' => $this->isSavedImage($src),
            'caption_word_count' => str_word_count($caption),
            'alt_word_count' => str_word_count($alt),
            'seo_score' => $this->calculateSeoScore($alt, $caption, $src),
            'accessibility_score' => $this->calculateAccessibilityScore($alt, $caption),
            'link_attributes' => $this->buildLinkAttributes($noFollow, $sponsored, $openInNewTab),
            'layout_css_class' => $this->getLayoutCssClass($layout),
            'is_responsive_layout' => $this->isResponsiveLayout($layout),
            'alignment' => $data['alignment'] ?? 'fullscreen',
            'alignment_css_class' => $this->getAlignmentCssClass($data['alignment'] ?? 'fullscreen'),
            'endorsements' => $endorsements,
            'has_endorsements' => !empty($endorsements),
            'endorsement_positions' => array_keys($endorsements)
        ];
    }

    private function getAlignmentCssClass(string $alignment): string
    {
        return 'image-align-' . $alignment;
    }

    private function parseBooleanValue($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    private function extractImageInfo(string $src): array
    {
        $extension = strtolower(pathinfo($src, PATHINFO_EXTENSION));
        $type = $this->getImageTypeFromExtension($extension);

        return [
            'extension' => $extension,
            'type' => $type
        ];
    }

    private function getImageTypeFromExtension(string $extension): string
    {
        $typeMap = [
            'jpg' => 'jpeg',
            'jpeg' => 'jpeg',
            'png' => 'png',
            'gif' => 'gif',
            'webp' => 'webp',
            'svg' => 'svg',
            'bmp' => 'bitmap',
            'tiff' => 'tiff',
            'ico' => 'icon'
        ];

        return $typeMap[$extension] ?? 'unknown';
    }

    private function isSavedImage(string $src): bool
    {
        // Check if the source appears to be a saved/uploaded image
        // This could be enhanced based on your specific storage patterns
        return strpos($src, '/uploads/') !== false ||
            strpos($src, '/saved-images/') !== false ||
            preg_match('/^[a-f0-9-]{36}\.(jpg|jpeg|png|gif|webp)$/i', basename($src));
    }

    private function isExternalLink(string $linkUrl): bool
    {
        if (empty($linkUrl)) {
            return false;
        }

        $parsedUrl = parse_url($linkUrl);

        // If no host is present, it's likely a relative URL
        if (!isset($parsedUrl['host'])) {
            return false;
        }

        // You might want to check against your domain here
        // For now, any URL with a host is considered external
        return true;
    }

    private function calculateSeoScore(string $alt, string $caption, string $src): int
    {
        $score = 0;

        // Alt text scoring
        if (!empty($alt)) {
            $score += 30;
            $altWordCount = str_word_count($alt);
            if ($altWordCount >= 3 && $altWordCount <= 15) {
                $score += 20; // Good alt text length
            }
        }

        // Caption scoring
        if (!empty($caption)) {
            $score += 20;
        }

        // Filename scoring (descriptive filename)
        $filename = pathinfo($src, PATHINFO_FILENAME);
        if (!preg_match('/^(img|image|photo|pic)[\d_-]*$/i', $filename)) {
            $score += 15; // Descriptive filename
        }

        // File type scoring
        $extension = strtolower(pathinfo($src, PATHINFO_EXTENSION));
        if (in_array($extension, ['webp', 'jpg', 'jpeg', 'png'])) {
            $score += 15; // Good web format
        }

        return min($score, 100);
    }

    private function calculateAccessibilityScore(string $alt, string $caption): int
    {
        $score = 0;

        // Alt text is required for accessibility
        if (!empty($alt)) {
            $score += 50;

            // Check alt text quality
            $altWordCount = str_word_count($alt);
            if ($altWordCount >= 2) {
                $score += 20; // Descriptive alt text
            }

            // Avoid redundant phrases
            $redundantPhrases = ['image of', 'picture of', 'photo of', 'graphic of'];
            $hasRedundant = false;
            foreach ($redundantPhrases as $phrase) {
                if (stripos($alt, $phrase) !== false) {
                    $hasRedundant = true;
                    break;
                }
            }
            if (!$hasRedundant) {
                $score += 15;
            }
        }

        // Caption can provide additional context
        if (!empty($caption)) {
            $score += 15;
        }

        return min($score, 100);
    }

    private function buildLinkAttributes(bool $noFollow, bool $sponsored, bool $openInNewTab): array
    {
        $attributes = [];

        if ($openInNewTab) {
            $attributes['target'] = '_blank';
            $attributes['rel'] = 'noopener noreferrer';
        }

        $relValues = [];
        if ($noFollow) {
            $relValues[] = 'nofollow';
        }
        if ($sponsored) {
            $relValues[] = 'sponsored';
        }

        if (!empty($relValues)) {
            if (isset($attributes['rel'])) {
                $attributes['rel'] .= ' ' . implode(' ', $relValues);
            } else {
                $attributes['rel'] = implode(' ', $relValues);
            }
        }

        return $attributes;
    }

    private function getLayoutCssClass(string $layout): string
    {
        return 'image-layout-' . $layout;
    }

    private function isResponsiveLayout(string $layout): bool
    {
        return in_array($layout, ['full', 'responsive', 'fluid']);
    }

    public function generateHtml(array $parsedData): string
    {
        $src = htmlspecialchars($parsedData['src'], ENT_QUOTES, 'UTF-8');
        $alt = htmlspecialchars($parsedData['alt'], ENT_QUOTES, 'UTF-8');
        $caption = $parsedData['formatted_caption'];
        $credit = $parsedData['formatted_credit'] ?? '';
        $shouldDisplayCredit = $parsedData['should_display_credit'] ?? false;
        $layoutClass = $parsedData['layout_css_class'];
        $alignmentClass = $parsedData['alignment_css_class'];

        $html = "<div class=\"image-block {$layoutClass} {$alignmentClass}\">";

        if (!empty($parsedData['linkUrl'])) {
            $linkUrl = htmlspecialchars($parsedData['linkUrl'], ENT_QUOTES, 'UTF-8');
            $linkAttrs = '';
            foreach ($parsedData['link_attributes'] as $attr => $value) {
                $linkAttrs .= " {$attr}=\"{$value}\"";
            }
            $html .= "<a href=\"{$linkUrl}\"{$linkAttrs}>";
        }

        $html .= "<img src=\"{$src}\" alt=\"{$alt}\" loading=\"lazy\">";

        if (!empty($parsedData['endorsements'])) {
            foreach ($parsedData['endorsements'] as $position => $endorsement) {
                $endorsementSrc = htmlspecialchars($endorsement['url'], ENT_QUOTES, 'UTF-8');
                $endorsementAlt = htmlspecialchars($endorsement['alt'] ?? 'Endorsement', ENT_QUOTES, 'UTF-8');
                $html .= "<img src=\"{$endorsementSrc}\" alt=\"{$endorsementAlt}\" class=\"endorsement-image {$position}\" loading=\"lazy\">";
            }
        }

        if (!empty($parsedData['linkUrl'])) {
            $html .= "</a>";
        }

        if (!empty($caption)) {
            $html .= "<figcaption>{$caption}</figcaption>";
        }

        // Only display credit if required by image rights
        if ($shouldDisplayCredit && !empty($credit)) {
            $html .= "<div class=\"image-credit\"><small>📷 {$credit}</small></div>";
        }

        $html .= "</div>";

        return $html;
    }

    private function getImageData(?int $imageId): array
    {
        if (!$imageId) {
            return [];
        }

        $image = Image::find($imageId);
        if (!$image) {
            return [];
        }

        return [
            'credit' => $image->credit,
            'image_rights' => $image->image_rights,
        ];
    }

    private function shouldDisplayCredit(?string $imageRights, string $credit): bool
    {
        // Always display credit if it exists and attribution is required
        if (empty($credit)) {
            return false;
        }

        if (empty($imageRights)) {
            return !empty($credit); // Display if credit exists but no rights specified
        }

        try {
            $rights = ImageRights::from($imageRights);
            return $rights->requiresAttribution();
        } catch (\ValueError $e) {
            return !empty($credit); // Fallback to showing credit if invalid rights
        }
    }
}