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

    /*
    |--------------------------------------------------------------------------
    | Embedding Provider
    |--------------------------------------------------------------------------
    |
    | The active embedding provider key, resolved through the settings facade
    | (`knowledge.embedding_provider`). `typesense` keeps the existing
    | managed-embedding mode where the vector store computes vectors
    | internally; `openai`, `cohere`, and `huggingface` compute vectors
    | client-side through App\Embedding\Services\EmbeddingManager.
    |
    */

    'embedding_provider' => env('KNOWLEDGE_EMBEDDING_PROVIDER', 'typesense'),

    /*
    |--------------------------------------------------------------------------
    | OCR Fallback
    |--------------------------------------------------------------------------
    |
    | When enabled, image files whose Tika extraction falls below
    | `ocr_min_content_chars` are passed to the local OCR service. The OCR
    | result only replaces Tika output when it is non-empty; otherwise the
    | original Tika result is kept. OCR is a targeted gap-filler for images —
    | Tika remains authoritative for every other file type.
    |
    */

    'ocr_enabled' => filter_var(env('KNOWLEDGE_OCR_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'ocr_min_content_chars' => (int) env('KNOWLEDGE_OCR_MIN_CONTENT_CHARS', 20),

    /*
    |--------------------------------------------------------------------------
    | OCR Image Extensions
    |--------------------------------------------------------------------------
    |
    | Canonical list of image extensions eligible for the OCR fallback.
    | A file is treated as an image when its MIME type starts with `image/`
    | or its extension is present here.
    |
    */

    'ocr_image_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp'],

    /*
    |--------------------------------------------------------------------------
    | MCP Browsing Resources
    |--------------------------------------------------------------------------
    */

    'mcp_document_page_size' => (int) env('KNOWLEDGE_MCP_DOCUMENT_PAGE_SIZE', 50),

    'mcp_source_document_summary' => (int) env('KNOWLEDGE_MCP_SOURCE_DOCUMENT_SUMMARY', 20),

    /*
    |--------------------------------------------------------------------------
    | Knowledge Source Templates
    |--------------------------------------------------------------------------
    |
    | Versioned presets offered by the create wizard. Templates are plain
    | configuration, not a database-managed CRUD system. They must never
    | contain passwords, bearer tokens, or encrypted credential material —
    | provider-specific secrets stay blank and are entered by the user.
    |
    */

    'source_templates' => [
        'markdown_docs' => [
            'label' => 'Markdown Documentation',
            'default_name' => 'Documentation',
            'namespace' => 'docs',
            'description' => 'Index markdown files rendered as documentation.',
            'provider_type' => 'markdown',
            'provider_config' => [],
        ],
        'filesystem_documents' => [
            'label' => 'Documents',
            'default_name' => 'Documents',
            'namespace' => 'documents',
            'description' => 'General document storage with file upload support.',
            'provider_type' => 'filesystem',
            'provider_config' => [],
        ],
        'web_docs' => [
            'label' => 'Website Documentation',
            'default_name' => 'Website Documentation',
            'namespace' => 'web-docs',
            'description' => 'Crawl a website or documentation portal.',
            'provider_type' => 'web',
            'provider_config' => ['urls' => []],
        ],
        'sql_table' => [
            'label' => 'SQL Table',
            'default_name' => 'SQL Table',
            'namespace' => 'database',
            'description' => 'Index rows from a database table.',
            'provider_type' => 'sql',
            'provider_config' => ['connection' => 'pgsql', 'table' => ''],
        ],
    ],

];
