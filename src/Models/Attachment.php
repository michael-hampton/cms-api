<?php

namespace App\Models;

/**
 * Columns:
 *   id                    int PK
 *   member_id             int FK → members.id
 *   site_id               int FK → sites.id
 *   attachmentable_type   varchar  (AttachmentableType enum value)
 *   attachmentable_id     int
 *   original_filename     varchar
 *   stored_path           varchar   (relative path from upload root)
 *   mime_type             varchar
 *   file_size             int       (bytes)
 *   uploaded_by           int FK → users.id
 *   created_at            datetime
 *   updated_at            datetime
 */
class Attachment extends Model
{
    protected $table = 'attachments';

    protected $fillable = [
        'member_id',
        'site_id',
        'attachmentable_type',
        'attachmentable_id',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size'  => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}