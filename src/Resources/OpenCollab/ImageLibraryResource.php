<?php

namespace App\Resources\OpenCollab;

use App\Framework\Resource\JsonResource;
use App\Models\Image;

class ImageLibraryResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id'                    => $this->getAttribute('id'),
            'name'                  => $this->getAttribute('name'),
            'thumbnail_url'         => $this->getAttribute('url'),
            'preview_url'           => $this->getAttribute('url'),
            'width'                 => $this->getAttribute('width'),
            'height'                => $this->getAttribute('height'),
            'mime_type'             => $this->getAttribute('mime_type'),
            'uploaded_at'           => $this->getAttribute('created_at'),
            'image_rights'          => $this->getAttribute('image_rights'),
            'image_rights_label'    => $this->resolveRightsLabel(),
            'alt_text'              => $this->getAttribute('alt_text'),
            'credit'                => $this->getAttribute('credit'),
//            'owned_by_current_user' => $this->ownedByCurrentUser,
//            'can_edit_metadata'     => $this->canEditMetadata,
        ];
    }

    private function resolveRightsLabel(): string
    {
        return match ($this->getAttribute('image_rights')) {
            'all_rights_reserved'  => 'All Rights Reserved',
            'attribution_required' => 'Attribution Required',
            'royalty_free'         => 'Royalty Free',
            'public_domain'        => 'Public Domain',
            'creative_commons'     => 'Creative Commons',
            'custom_license'       => 'Custom License',
            'contributor_owned'    => 'Contributor-owned',
            'staff_owned'          => 'Staff-owned',
            'third_party_licensed' => 'Licensed third-party image',
            'agency'               => 'Agency image',
            'editorial_use_only'   => 'Editorial use only',
            default                => 'Rights not confirmed',
        };
    }
}