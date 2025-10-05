<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class ImageResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'filename' => $this->getAttribute('filename'),
            'original_name' => $this->getAttribute('original_name'),
            'url' => $this->getAttribute('url'),
            'mime_type' => $this->getAttribute('mime_type'),
            'file_size' => $this->getAttribute('file_size'),
            'width' => $this->getAttribute('width'),
            'height' => $this->getAttribute('height'),
            'alt_text' => $this->getAttribute('alt_text'),
            'caption' => $this->getAttribute('caption'),
            'description' => $this->getAttribute('description'),
            'created_at' => $this->getAttribute('created_at'),
            'updated_at' => $this->getAttribute('updated_at'),
            'categories' => $this->whenLoaded('categories'),
        ];
    }
}