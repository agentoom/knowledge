<?php

namespace Database\Seeders;

use App\Jobs\DocumentPipeline\IndexChunk;
use App\Knowledge\Models\Chunk;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use App\Knowledge\Models\Provider;
use App\Models\ApiKey;
use App\VectorStore\Models\VectorStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class KnowledgeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $filesystemSource = KnowledgeSource::firstOrCreate(
            ['slug' => 'documentation-files'],
            [
                'name' => 'Documentation Files',
                'description' => 'Local documentation in markdown and text files.',
                'provider_type' => 'filesystem',
                'provider_config' => [
                    'basePath' => base_path('storage/app/knowledge/docs'),
                ],
                'namespace' => 'docs',
                'is_active' => true,
                'priority' => 10,
                'config_version' => 1,
            ]
        );

        $sqlSource = KnowledgeSource::firstOrCreate(
            ['slug' => 'company-database'],
            [
                'name' => 'Company Database',
                'description' => 'SQL database with structured business data.',
                'provider_type' => 'sql',
                'provider_config' => [
                    'connection' => 'pgsql',
                    'table' => 'knowledge_demo_products',
                ],
                'namespace' => 'erp',
                'is_active' => true,
                'priority' => 20,
                'config_version' => 1,
            ]
        );

        $yamlSource = KnowledgeSource::firstOrCreate(
            ['slug' => 'configuration-files'],
            [
                'name' => 'Configuration Files',
                'description' => 'YAML configuration and metadata files.',
                'provider_type' => 'yaml',
                'provider_config' => [
                    'basePath' => base_path('storage/app/knowledge/config'),
                ],
                'namespace' => 'config',
                'is_active' => true,
                'priority' => 30,
                'config_version' => 1,
            ]
        );

        $globalSemanticSource = KnowledgeSource::firstOrCreate(
            ['slug' => 'global-semantic-search'],
            [
                'name' => 'Global Semantic Search',
                'description' => 'Semantic search across all indexed knowledge sources.',
                'provider_type' => 'vector_store',
                'namespace' => 'global',
                'is_active' => true,
                'priority' => 100,
                'config_version' => 1,
            ]
        );

        foreach ([$filesystemSource, $sqlSource, $yamlSource, $globalSemanticSource] as $source) {
            Provider::firstOrCreate(
                [
                    'knowledge_source_id' => $source->id,
                    'type' => $source->provider_type,
                ],
                [
                    'class' => match ($source->provider_type) {
                        'filesystem' => 'App\\Providers\\Filesystem\\FilesystemProvider',
                        'sql' => 'App\\Providers\\Sql\\SqlProvider',
                        'yaml' => 'App\\Providers\\Yaml\\YamlProvider',
                        'vector_store' => 'App\\Providers\\VectorStore\\SemanticProvider',
                        default => 'App\\Providers\\Filesystem\\FilesystemProvider',
                    },
                    'name' => $source->name.' Provider',
                    'metadata' => [
                        'namespace' => $source->namespace,
                        'capabilities' => $source->provider_type === 'vector_store'
                            ? ['search', 'semantic_search']
                            : ['search', 'list_resources'],
                    ],
                    'status' => 'active',
                    'last_synced_at' => now(),
                ]
            );
        }

        $sampleFilenames = [
            'getting-started.md', 'api-reference.md', 'architecture.md',
            'deployment-guide.txt', 'changelog.md', 'faq.md',
            'database-schema.sql', 'business-rules.yaml', 'config.yaml',
            'data-catalog.json',
        ];

        foreach ($sampleFilenames as $i => $filename) {
            $source = match (true) {
                str_ends_with($filename, '.sql') => $sqlSource,
                str_ends_with($filename, '.yaml') => $yamlSource,
                default => $filesystemSource,
            };

            $document = Document::firstOrCreate(
                [
                    'knowledge_source_id' => $source->id,
                    'filename' => $filename,
                ],
                [
                    'path' => '/data/'.$source->namespace.'/'.$filename,
                    'mime_type' => 'text/plain',
                    'size_bytes' => rand(1024, 102400),
                    'content' => "Sample content for {$filename}.\n\nThis is auto-generated demo content for testing the knowledge pipeline.",
                    'status' => 'chunked',
                    'chunked_at' => now(),
                ]
            );

            $chunkCount = rand(1, 4);
            for ($j = 0; $j < $chunkCount; $j++) {
                $chunk = Chunk::firstOrCreate(
                    [
                        'document_id' => $document->id,
                        'sequence' => $j,
                    ],
                    [
                        'content' => "Chunk {$j} of {$filename}: This is sample chunk content for testing indexing and search.",
                        'token_count' => rand(20, 100),
                        'embedding_hash' => md5("{$filename}-{$j}"),
                        'metadata' => [
                            'document_filename' => $filename,
                            'source_namespace' => $source->namespace,
                        ],
                    ]
                );

                IndexChunk::dispatch($chunk->id);
            }
        }

        $adminKey = 'ak-'.bin2hex(random_bytes(16));
        ApiKey::firstOrCreate(
            ['name' => 'Admin API Key'],
            [
                'key' => Hash::make($adminKey),
                'scopes' => ['admin:*', 'mcp:use'],
                'last_used_at' => null,
                'expires_at' => null,
            ]
        );

        $readonlyKey = 'rk-'.bin2hex(random_bytes(16));
        ApiKey::firstOrCreate(
            ['name' => 'Read-only API Key'],
            [
                'key' => Hash::make($readonlyKey),
                'scopes' => ['mcp:use'],
                'last_used_at' => null,
                'expires_at' => now()->addYear(),
            ]
        );

        VectorStore::firstOrCreate(
            ['driver' => 'typesense'],
            [
                'config' => [
                    'host' => 'typesense',
                    'port' => 8108,
                    'protocol' => 'http',
                    'api_key' => 'xyz',
                ],
                'is_active' => true,
            ]
        );

        $this->command->info('Demo data seeded successfully!');
        $this->command->info("Admin API Key: {$adminKey}");
        $this->command->info("Read-only API Key: {$readonlyKey}");
    }
}
