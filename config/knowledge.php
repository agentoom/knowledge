<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Knowledge Base Path
    |--------------------------------------------------------------------------
    |
    | Root directory where knowledge source files are stored. Each provider
    | type gets its own subdirectory, and each namespace is a subdirectory
    | within that. Files can be uploaded via the UI or placed directly on
    | the server filesystem.
    |
    | Structure: {base_path}/{provider_type}/{namespace}/
    |
    | Example: /var/www/storage/app/knowledge/yaml/my-docs/
    |
    */

    'base_path' => env('KNOWLEDGE_BASE_PATH', storage_path('app/knowledge')),

    /*
    |--------------------------------------------------------------------------
    | Allowed File Extensions
    |--------------------------------------------------------------------------
    |
    | Extensions permitted for upload and discovery per provider type.
    | The 'generic' type accepts any extension listed here.
    |
    */

    'allowed_extensions' => [
        'yaml' => ['yml', 'yaml'],
        'json' => ['json'],
        'markdown' => ['md', 'mdx'],
        'filesystem' => ['txt', 'md', 'pdf', 'doc', 'docx', 'html', 'csv', 'json', 'yml', 'yaml', 'xml'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tika-enabled Extensions (requires Apache Tika)
    |--------------------------------------------------------------------------
    |
    | When Tika is reachable, the filesystem provider expands to accept all
    | formats Tika can parse. Documents, spreadsheets, slides, PDFs, ebooks,
    | email and archives get full content extraction. Images (jpg, png, etc.)
    | are indexed for metadata only (EXIF, dimensions, filename) — Tika does
    | not perform OCR without the Tesseract module.
    |
    | See: https://tika.apache.org/2.9.1/formats.html
    |
    */

    'tika_enabled_extensions' => [
        // Plain text & markup
        'txt', 'csv', 'log', 'html', 'htm', 'xml', 'json', 'md', 'rst',
        // PDF
        'pdf',
        // Microsoft Office
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        // OpenDocument
        'odt', 'ods', 'odp', 'odg',
        // Rich Text
        'rtf',
        // E-books
        'epub',
        // Images (OCR)
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp',
        // Email
        'eml', 'msg',
        // Archives (metadata + embedded content)
        'zip', 'tar', 'gz',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Upload File Size (KB)
    |--------------------------------------------------------------------------
    */

    'max_upload_size_kb' => (int) env('KNOWLEDGE_MAX_UPLOAD_SIZE_KB', 512000),

    /*
    |--------------------------------------------------------------------------
    | Maximum Scan Depth
    |--------------------------------------------------------------------------
    |
    | Maximum directory depth for recursive file discovery. Prevents
    | performance issues with deeply nested directory structures.
    | Set to 0 for unlimited depth.
    |
    */

    'max_scan_depth' => (int) env('KNOWLEDGE_MAX_SCAN_DEPTH', 5),

];
