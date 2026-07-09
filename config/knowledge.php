<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Custom Knowledge Providers
    |--------------------------------------------------------------------------
    |
    | Register custom provider types here. The key is the provider_type value
    | used in the admin UI, and the value is the fully-qualified class name.
    |
    | These providers are auto-discovered by the KnowledgeSourceObserver
    | and the ProviderManager.
    |
    | Example:
    |   'salesforce' => App\Providers\Salesforce\SalesforceProvider::class,
    |   'jira'       => App\Providers\Jira\JiraProvider::class,
    |
    */
    'custom_providers' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Chunking Configuration
    |--------------------------------------------------------------------------
    |
    | These defaults are used by the ChunkingStrategyRegistry when no
    | per-document overrides are specified.
    |
    */
    'chunking' => [
        'default_max_size' => 1500,
        'default_min_size' => 200,
        'sliding_window_size' => 1200,
        'sliding_window_stride' => 800,
    ],

];
