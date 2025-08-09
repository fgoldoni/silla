<?php

return [
    'disk' => env('DOCUMENTS_DISK', 'local'),
    'max_size' => env('DOCUMENTS_MAX_SIZE', 20 * 1024 * 1024), // 20 Mo
    'allowed_mimes' => [
        'application/pdf',
        'image/png', 'image/jpeg',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
        'text/plain', 'text/csv',
    ],
    'signed_ttl' => env('DOCUMENTS_SIGNED_TTL', 900), // 15 min
];
