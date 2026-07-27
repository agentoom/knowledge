<?php

namespace App\Knowledge\Models;

use Database\Factories\ChunkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $metadata
 * @property Document|null $document
 */
/**
 * @mixin HasFactory<ChunkFactory>
 */
class Chunk extends Model
{
    /** @use HasFactory<ChunkFactory> */
    use HasFactory;

    protected static function newFactory(): ChunkFactory
    {
        return ChunkFactory::new();
    }

    protected $fillable = [
        'document_id',
        'sequence',
        'content',
        'token_count',
        'embedding_hash',
        'metadata',
        'indexed_at',
        'vector_store_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'indexed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
