<?php
return [
// Upload directory relative to project root
    'path' => env('UPLOAD_PATH', 'storage/uploads'),

// Maximum file size in bytes (10MB default)
    'max_file_size' => env('MAX_FILE_SIZE', 10 * 1024 * 1024),

// Allowed mime types for images
    'allowed_mime_types' => [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml'
    ],

// Thumbnail sizes
    'thumbnail_sizes' => [
        'small' => [150, 150],
        'medium' => [300, 300],
        'large' => [600, 600]
    ],

// Image optimization settings
    'optimize_images' => env('OPTIMIZE_IMAGES', true),
    'jpeg_quality' => env('JPEG_QUALITY', 85),
    'png_compression' => env('PNG_COMPRESSION', 7),
    'webp_quality' => env('WEBP_QUALITY', 85),

// Auto-generate thumbnails on upload
    'auto_thumbnails' => env('AUTO_THUMBNAILS', true),

// Delete physical files when soft deleting
    'soft_delete_files' => env('SOFT_DELETE_FILES', false),
];