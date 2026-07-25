<?php

namespace App\Knowledge\Models;

use App\Contracts\KnowledgeProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @property array<string, mixed>|null $metadata
 * @property int|null $knowledge_source_id
 * @property KnowledgeSource|null $knowledgeSource
 */
class Provider extends Model
{
    protected $fillable = [
        'knowledge_source_id',
        'class',
        'name',
        'type',
        'metadata',
        'status',
        'last_synced_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<KnowledgeSource, $this>
     */
    public function knowledgeSource(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class);
    }

    /**
     * Instantiate the KnowledgeProvider class from the stored class name.
     * Returns null if the class cannot be instantiated (e.g., missing dependencies).
     *
     * For filesystem-backed providers, the basePath is derived from the knowledge
     * source's namespace using the canonical path convention, unless a custom
     * basePath is explicitly set in provider_config.
     */
    public function toKnowledgeProvider(): ?KnowledgeProvider
    {
        if (! class_exists($this->class)) {
            return null;
        }

        try {
            $source = $this->knowledgeSource;
            $config = is_array($source?->provider_config) ? $source->provider_config : [];

            // For filesystem-backed providers, resolve canonical path from namespace
            // if no explicit basePath is configured.
            if (method_exists($this->class, 'canonicalPath') && empty($config['basePath']) && $source) {
                $config['basePath'] = $this->class::canonicalPath($source->namespace);
            }

            $instance = app()->makeWith($this->class, $config);

            if ($instance instanceof KnowledgeProvider) {
                return $instance;
            }
        } catch (Throwable $e) {
            Log::debug('Failed to instantiate provider class.', [
                'class' => $this->class,
                'provider_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
