<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed>|null $remote_capabilities
 */
class FederatedServer extends Model
{
    protected $fillable = [
        'name',
        'endpoint_url',
        'auth_token',
        'is_active',
        'priority',
        'remote_capabilities',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'remote_capabilities' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }
}
