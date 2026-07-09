<?php

namespace App\Knowledge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $metadata
 * @property Document|null $document
 */
class Chunk extends Model
{
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

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
