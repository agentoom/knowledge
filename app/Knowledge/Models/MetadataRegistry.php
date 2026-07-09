<?php

namespace App\Knowledge\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed> $payload
 */
class MetadataRegistry extends Model
{
    protected $table = 'metadata_registry';

    protected $fillable = [
        'payload',
        'version',
        'checksum',
        'built_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'built_at' => 'datetime',
        ];
    }
}
