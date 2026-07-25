<?php

namespace App\Knowledge\Models;

use App\Jobs\DocumentPipeline\DeindexDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $metadata
 * @property array<string, mixed>|null $provider_config
 * @property KnowledgeSource|null $knowledgeSource
 */
class Document extends Model
{
    protected $fillable = [
        'knowledge_source_id',
        'path',
        'filename',
        'mime_type',
        'size_bytes',
        'content',
        'content_hash',
        'status',
        'metadata',
        'parsed_at',
        'chunked_at',
        'indexed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'parsed_at' => 'datetime',
            'chunked_at' => 'datetime',
            'indexed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Document $document) {
            // Capture chunk IDs before DB cascade-deletion removes them,
            // then dispatch a job to remove them from the search index.
            $chunkIds = $document->chunks()->pluck('id')->toArray();

            if (! empty($chunkIds)) {
                DeindexDocument::dispatch($chunkIds);
            }
        });
    }

    /**
     * @return BelongsTo<KnowledgeSource, $this>
     */
    public function knowledgeSource(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class);
    }

    /**
     * @return HasMany<Chunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class);
    }
}
