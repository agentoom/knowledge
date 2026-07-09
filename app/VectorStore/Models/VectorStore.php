<?php

namespace App\VectorStore\Models;

use Illuminate\Database\Eloquent\Model;

class VectorStore extends Model
{
    protected $table = 'vector_stores';

    protected $fillable = [
        'driver',
        'config',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
