<?php

declare(strict_types=1);

namespace App\Resources;

use App\Framework\Resource\JsonResource;

/**
 * API representation of a PrintVendorConnection.
 *
 * Deliberately never includes the password, even encrypted — only a
 * `has_password` boolean, so the admin UI can show "configured" /
 * "not configured" without ever handling the secret itself. The edit
 * form must treat a blank password field as "leave unchanged"
 * (see PrintVendorConnectionService::update()).
 */
class PrintVendorConnectionResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'name' => $this->getAttribute('name'),
            'code' => $this->getAttribute('code'),
            'connection_type' => $this->getAttribute('connection_type'),
            'host' => $this->getAttribute('host'),
            'port' => $this->getAttribute('port'),
            'username' => $this->getAttribute('username'),
            'has_password' => !empty($this->getAttribute('password')),
            'remote_path' => $this->getAttribute('remote_path'),
            'is_active' => $this->getAttribute('is_active'),
            'is_default' => $this->getAttribute('is_default'),
            'notes' => $this->getAttribute('notes'),
            'last_tested_at' => $this->getAttribute('last_tested_at')?->format('Y-m-d H:i:s'),
            'last_test_status' => $this->getAttribute('last_test_status'),
            'last_test_message' => $this->getAttribute('last_test_message'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),
        ];
    }
}