<?php

return [
    'documents' => [
        'disk' => 'local',
        'base_path' => 'storage/open-collab-documents',
        'max_upload_mb' => 10,
        'allowed_extensions' => ['pdf', 'docx', 'txt', 'md'],
        'allowed_mime_types' => [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'text/markdown',
            'application/octet-stream',
        ],
    ],
];
