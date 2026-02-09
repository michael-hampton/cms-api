<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\ContactFormBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class ContactFormBlockRenderer implements EmailBlockRenderer
{
    public function supports(string $type): bool
    {
        return $type === 'contact-form';
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof ContactFormBlockData) {
            return RenderedBlock::skipped();
        }

        $html = [];
        $html[] = '<div style="background-color: #f8f9fa; border-radius: 8px; padding: 25px; margin: 20px 0;">';

        $html[] = '<h3 style="color: #333; margin: 0 0 10px 0; font-size: 22px;">' . Str::sanitize($blockData->title) . '</h3>';

        if ($blockData->subtitle) {
            $html[] = '<p style="color: #666; margin: 0 0 20px 0; font-size: 16px;">' . Str::sanitize($blockData->subtitle) . '</p>';
        }

        if (!empty($blockData->contactInfo['email'])) {
            $html[] = '<div style="margin-bottom: 15px;">';
            $html[] = '<span style="font-size: 20px; margin-right: 10px;">✉️</span>';
            $html[] = '<strong style="color: #333;">Email:</strong> ';
            $html[] = '<a href="mailto:' . Str::sanitize($blockData->contactInfo['email']) . '" style="color: #007bff; text-decoration: none;">' . Str::sanitize($blockData->contactInfo['email']) . '</a>';
            $html[] = '</div>';
        }

        if (!empty($blockData->contactInfo['phone'])) {
            $html[] = '<div style="margin-bottom: 15px;">';
            $html[] = '<span style="font-size: 20px; margin-right: 10px;">📞</span>';
            $html[] = '<strong style="color: #333;">Phone:</strong> ';
            $html[] = '<a href="tel:' . Str::sanitize($blockData->contactInfo['phone']) . '" style="color: #007bff; text-decoration: none;">' . Str::sanitize($blockData->contactInfo['phone']) . '</a>';
            $html[] = '</div>';
        }

        if (!empty($blockData->contactInfo['address'])) {
            $address = $blockData->contactInfo['address'];
            $html[] = '<div style="margin-bottom: 15px;">';
            $html[] = '<span style="font-size: 20px; margin-right: 10px;">📍</span>';
            $html[] = '<strong style="color: #333;">Address:</strong><br>';
            $html[] = '<span style="color: #666; margin-left: 30px;">';
            $html[] = Str::sanitize($address['line1'] ?? '');
            if (!empty($address['line2'])) {
                $html[] = '<br>' . Str::sanitize($address['line2']);
            }
            $html[] = '<br>' . Str::sanitize($address['city'] ?? '') . ', ' . Str::sanitize($address['postcode'] ?? '');
            $html[] = '</span>';
            $html[] = '</div>';
        }

        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}