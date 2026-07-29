<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<int, string> $words
 */
class SynonymGroup extends Model
{
    protected $fillable = [
        'tenant_id',
        'words',
    ];

    protected function casts(): array
    {
        return [
            'words' => 'array',
        ];
    }
}
