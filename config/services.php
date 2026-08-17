<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Providers
    |--------------------------------------------------------------------------
    |
    | Credentials and connection defaults for external embedding providers.
    | API secrets are read from the environment only — they are never stored
    | in the settings table. Non-secret connection values (model, endpoint,
    | dimensions) default here and may be overridden from the Embedding
    | settings tab.
    |
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'embedding_dimensions' => (int) (env('OPENAI_EMBEDDING_DIMENSIONS') ?: 1536),
    ],

    'cohere' => [
        'api_key' => env('COHERE_API_KEY'),
        'base_url' => env('COHERE_BASE_URL', 'https://api.cohere.com/v1'),
        'embedding_model' => env('COHERE_EMBEDDING_MODEL', 'embed-english-v3.0'),
        'embedding_dimensions' => (int) (env('COHERE_EMBEDDING_DIMENSIONS') ?: 1024),
    ],

    'huggingface' => [
        'endpoint' => env('HUGGINGFACE_EMBEDDING_ENDPOINT', 'http://tei:8080/embed'),
        'model' => env('HUGGINGFACE_EMBEDDING_MODEL', 'sentence-transformers/all-MiniLM-L6-v2'),
        'embedding_dimensions' => (int) (env('HUGGINGFACE_EMBEDDING_DIMENSIONS') ?: 384),
        'api_token' => env('HUGGINGFACE_API_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Parsing Services
    |--------------------------------------------------------------------------
    */

    'tika' => [
        'endpoint' => env('TIKA_ENDPOINT', 'http://tika:9998'),
    ],

    'ocr' => [
        'endpoint' => env('OCR_ENDPOINT', 'http://ocr:8000'),
    ],

];
