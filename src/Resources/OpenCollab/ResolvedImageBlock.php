<?php

namespace App\Resources\OpenCollab;

use App\Framework\Resource\JsonResource;

/**
 * Editor-facing view model for an image block.
 *
 * resolved_image is populated at load time and is NOT stored in the block.
 * The block's own alt and credit fields are authoritative for the article.
 */
final class ResolvedImageBlock extends JsonResource
{

    public function toArray(): array
    {
        return [
            'name' => $this->getAttribute('name'),
            'thumbnail_url' => $this->getAttribute('thumbnail_url'),
            'preview_url' => $this->getAttribute('preview_url'),
            'width' => $this->getAttribute('width'),
            'height' => $this->getAttribute('height'),
            'image_rights' => $this->getAttribute('image_rights'),
            'alt_text' => $this->getAttribute('alt'),  // canonical CMS value (for reset)
            'credit' => $this->getAttribute('credit'),    // canonical CMS value (for reset)
            'status' => $this->getAttribute('is_active') ? 'active' : 'inactive',
        ];
    }
}