<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    use HasFactory;

    /**
     * The plain-text key value. Only available immediately after creation.
     */
    public ?string $plainKey = null;

    protected $fillable = [
        'user_id',
        'name',
        'key',
        'scopes',
        'knowledge_namespaces',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'knowledge_namespaces' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
