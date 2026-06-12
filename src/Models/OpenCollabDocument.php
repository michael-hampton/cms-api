<?php

namespace App\Models;

class OpenCollabDocument extends Model
{
    protected $table = 'open_collab_documents';

    protected $fillable = [
        'site_id',
        'documentable_type',
        'documentable_id',
        'category',
        'original_filename',
        'stored_filename',
        'disk',
        'path',
        'mime_type',
        'extension',
        'size_bytes',
        'checksum',
        'uploaded_by_user_id',
        'metadata_json',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'documentable_id' => 'integer',
        'size_bytes' => 'integer',
        'metadata_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
